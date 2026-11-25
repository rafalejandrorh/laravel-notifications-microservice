# Arquitectura

Microservicio Laravel 13 (PHP 8.3) para enviar notificaciones. **v1 envía solo email.** Push y SMS tienen contrato JSON, colas e inbox en MongoDB; no hay workers ni envío real de esos canales.

El productor describe *qué* enviar. Este servicio decide *con qué proveedor*. El contrato de uso (payloads y API) está en el [README](../README.md). Las decisiones de diseño están en [design.md](design.md). El mapa de clases está en [components.md](components.md).

## Vista general

Hay **una API HTTP, un núcleo y un driver de cola**. HTTP persiste el inbox y publica al driver (`NOTIFICATION_QUEUE_DRIVER`). El worker (Messenger o `queue:work`) y, solo en modo `rabbitmq`, los productores AMQP convergen en `NotificationDispatchService`, que reclama el envío y delega al canal.

```mermaid
flowchart LR
  Producer[Productor]
  Api["POST /api/emails"]
  Enqueue[NotificationEnqueueService]
  QueueIface[NotificationQueue]
  Dispatch[NotificationDispatchService]
  Inbox[(MongoDB inbox_events)]
  Channel[EmailChannel]
  Provider[Mail adapters]

  Producer --> Api
  Api --> Enqueue
  Enqueue --> Inbox
  Enqueue --> QueueIface
  QueueIface --> Dispatch
  Dispatch --> Inbox
  Dispatch --> Channel --> Provider
```

`NOTIFICATION_QUEUE_DRIVER`: `rabbitmq` (default) o `laravel`. Infra local: MongoDB 7; RabbitMQ 3 solo si el driver es `rabbitmq` (`docker compose up -d`). La app PHP no corre en Compose.

## HTTP (asíncrono)

Rutas en [`routes/api.php`](../routes/api.php), prefijo `/api`.

1. `AuthenticateApiKey` valida el header `X-API-Key` (salvo `GET /health`).
2. `POST /emails` → [`EmailController::store`](../app/Http/Controllers/EmailController.php) → [`StoreEmailRequest::toMessage()`](../app/Http/Requests/StoreEmailRequest.php).
3. Si no viene `event_id`, se genera un UUID. Se eliminan `payload.provider` y `payload.from`.
4. [`NotificationEnqueueService::enqueue`](../app/Services/NotificationEnqueueService.php) persiste el inbox (`received`) y publica con [`NotificationQueue::publish`](../app/Contracts/NotificationQueue.php).
5. Responde `202` con `status: received`. Si el publish falla, `503`.

## Drivers de cola

### `rabbitmq` (pub/sub AMQP)

Dos entradas: HTTP y mensajes JSON al exchange. [`RabbitMqNotificationQueue`](../app/Queue/RabbitMqNotificationQueue.php) delega a [`MessengerFactory::send`](../app/Messenger/MessengerFactory.php).

El `bus()` de Messenger sigue siendo solo de consumo (`HandleMessageMiddleware`). No se mezcla send y handle en el mismo bus.

1. El productor (esta API u otro servicio) publica JSON al exchange topic (`MESSENGER_EXCHANGE`, en `.env.example`: `notificaciones`).
2. `php artisan messenger:consume email` arranca el worker ([`MessengerConsumeCommand`](../app/Console/Commands/MessengerConsumeCommand.php)).
3. [`MessengerFactory`](../app/Messenger/MessengerFactory.php) declara topología, deserializa con [`JsonMessageSerializer`](../app/Messenger/JsonMessageSerializer.php) y enruta al handler.
4. `SendEmailMessageHandler` llama a `NotificationDispatchService::dispatch`. Transitorios se relanzan como `RecoverableNotificationException`; tipos desconocidos como `UnrecoverableNotificationException`.

El serializer es JSON interoperable (no el formato PHP de Symfony). Si el `event_type` es desconocido o el envelope es inválido, el mensaje se convierte en `UnsupportedNotificationMessage` (fallo permanente).

### `laravel` (solo API HTTP)

No hay pub/sub AMQP. Los eventos entran solo por `POST /api/emails`. [`LaravelNotificationQueue`](../app/Queue/LaravelNotificationQueue.php) despacha [`SendNotificationJob`](../app/Jobs/SendNotificationJob.php) (`event_id`) a la cola del canal (`email.send`, `push.send`, `sms.send`). `php artisan queue:work --queue=email.send` carga el inbox y llama a `NotificationDispatchService`. El job no sustituye al inbox: Laravel borra la fila de `jobs` al terminar; el historial sigue en Mongo.

v1 solo consume email (como Messenger). Escalar es más procesos del mismo comando. Push/SMS se encolan en su cola pero no se levantan workers hasta que el canal esté habilitado.

`QUEUE_CONNECTION` elige el backend (`database`, `redis`, …). No usar `sync` en producción. `messenger:setup` y `messenger:consume` se niegan.

## Inbox (MongoDB)

Colección `inbox_events`, modelo [`InboxEvent`](../app/Models/InboxEvent.php), acceso vía [`InboxEventRepository`](../app/Repositories/InboxEventRepository.php).

Índices (`php artisan inbox:ensure-indexes`):

- único en `event_id`
- único sparse en `(channel, idempotency_key)` — la misma clave puede usarse en email y SMS

### Ciclo de vida

```mermaid
stateDiagram-v2
  [*] --> received: persistNew
  received --> processing: claim
  failed --> processing: claim si retryable
  processing --> processing: reclaim si claim_ttl expiró
  processing --> sent: markSent
  processing --> failed: markFailed
```

| Estado | Rol |
|--------|-----|
| `received` | Recién persistido o reintento manual |
| `processing` | Claim atómico (`findOneAndUpdate`); incrementa `attempts` |
| `sent` | Terminal de éxito |
| `failed` | Error; `retryable` true/false |
| `skipped_duplicate` | Terminal; existe en el enum pero el dispatch de producción no lo asigna |

`NotificationDispatchService::dispatch`:

1. `persistNew` → `received`. Si hay clave duplicada (E11000), recarga el documento existente.
2. Si el estado es terminal (`sent` / `skipped_duplicate`), o es un duplicado `failed` no retryable, **sale sin enviar**.
3. `claim` atómico. Si otro worker tiene el claim vigente, devuelve el evento en vuelo.
4. Si `attempts > notifications.max_send_attempts` (default 5), marca `failed` permanente.
5. Resuelve el canal. Si `supported()` es false, `failed` permanente.
6. Si no hay contenido renderizado, `channel->render()` y `storeRendered()`.
7. `channel->send()` → `markSent`. Permanente → `markFailed(..., retryable: false)`. Cualquier otro `Throwable` → `markFailed(..., retryable: true)` **y relanza** (Messenger reintenta).

Claim reclaimable si: `received`; o `failed` + `retryable`; o `processing` con `claimed_at` más viejo que `notifications.claim_ttl_seconds` (default 300). El `worker_id` es `hostname:pid`.

Reintento manual: `POST /api/emails/{eventId}/retry` resetea a `received` y vuelve a `enqueue` (publica; no envía en el request). Solo email, solo si no está `sent`.

## Messenger

Configuración en [`config/messenger.php`](../config/messenger.php).

El nombre del exchange lo define `MESSENGER_EXCHANGE`. En `.env.example` vale `notificaciones`. Si la variable no está, el fallback de config es `notifications`.

| Canal | Routing key | Cola | DLQ | Worker v1 |
|-------|-------------|------|-----|-----------|
| email | `email.send` | `email.send` | `email.send.dlq` | sí (`consume: true`) |
| push | `push.send` | `push.send` | `push.send.dlq` | no (cola declarada) |
| sms | `sms.send` | `sms.send` | `sms.send.dlq` | no (cola declarada) |

Retry del worker: `MultiplierRetryStrategy` (`MESSENGER_MAX_RETRIES`, delay, multiplier, max delay). Tras agotar reintentos, el mensaje va a la DLQ. Excepciones `UnrecoverableExceptionInterface` (permanentes) no entran en ese ciclo.

`messenger:setup` declara exchange, colas y bindings. `messenger:consume push|sms` falla a propósito: *"no se consume en v1"*.

## Pipeline email

```mermaid
flowchart TD
  Payload[payload template XOR content]
  Resolver[EmailContentResolver]
  Catalog[TemplateCatalog]
  Blade[TemplateRenderer Markdown]

  Rendered[RenderedNotification]
  Send[EmailChannel.send]
  MailResolver[MailProviderResolver]
  Adapter[Adapter log smtp mailgun gmail]
  Failover[FailoverMailAdapter opcional]

  Payload --> Resolver
  Resolver -->|template| Catalog --> Blade --> Rendered
  Resolver -->|content| Rendered
  Rendered --> Send --> MailResolver --> Adapter
  MailResolver -.->|MAIL_FAILOVER_MAILER| Failover --> Adapter
```

- **Render:** [`EmailChannel::render`](../app/Channels/Email/EmailChannel.php) → [`EmailContentResolver`](../app/Channels/Email/EmailContentResolver.php). Exactamente uno de `template` o `content`. Plantilla: [`TemplateCatalog`](../app/Channels/Email/TemplateCatalog.php) + vistas Markdown `resources/views/notifications/email/{nombre}/v{n}.blade.php` (`<x-mail::message>`, tema `sivacrim` con texto centrado). Sin `version` se usa `latest` de [`config/notification_templates.php`](../config/notification_templates.php). La versión y el `from` resueltos se persisten en el inbox.
- **Send:** construye `RenderedEmail` (destinatarios del payload + `from` del catálogo / identidades + imágenes CID si el HTML las referencia) y llama a [`MailProviderResolver`](../app/Channels/Email/MailProviderResolver.php).

| `MAIL_MAILER` | Adapter |
|---------------|---------|
| `smtp`, `sendmail` | `SmtpMailAdapter` |
| `mailgun` | `MailgunMailAdapter` |
| `gmail` | `GmailMailAdapter` (API, service account) |
| `log`, `array` | `LogMailAdapter` |

Si `MAIL_FAILOVER_MAILER` está definido y es distinto del primario, se envuelve en `FailoverMailAdapter`.

## Auth, API y operaciones

Auth: header `X-API-Key` frente a `NOTIFICATIONS_API_KEY`. `GET /api/health` no exige clave; comprueba app, MongoDB y, según el driver, RabbitMQ o la cola Laravel (`ok` 200 / `degraded` 503).

Rutas protegidas: `POST /emails`, `GET /notifications/{eventId}`, `POST /emails/{eventId}/retry`, `GET /templates`. No hay `POST /api/push` ni `POST /api/sms` en v1.

Comandos:

| Comando | Rol |
|---------|-----|
| `inbox:ensure-indexes` | Índices únicos del inbox |
| `messenger:setup` | Exchange, colas, DLQs (solo driver `rabbitmq`) |
| `messenger:consume email` | Worker de email (solo driver `rabbitmq`) |
| `queue:work --queue=email.send` | Worker Laravel de email (solo driver `laravel`; más réplicas para escalar) |

## Límites v1

[`ChannelRegistry`](../app/Channels/ChannelRegistry.php) registra email, push y SMS. [`PushChannel`](../app/Channels/Push/PushChannel.php) y [`SmsChannel`](../app/Channels/Sms/SmsChannel.php) tienen `supported() = false`; render/send lanzan `ChannelNotEnabledException`. El dispatch marca esos eventos como `failed` permanente. Las colas existen para que los productores puedan publicar ya; el worker las rechaza.

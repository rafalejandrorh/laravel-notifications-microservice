# Componentes

Catálogo por capa. No es un inventario de cada archivo; es el mapa de responsabilidades. Flujo: [architecture.md](architecture.md). Decisiones: [design.md](design.md).

## Capas

| Capa | Dónde | Rol |
|------|--------|-----|
| HTTP | `app/Http/` | API key, validación, 202, retry, estado, catálogo, health |
| Dominio mensajes | `app/Message/` | Envelope JSON → DTO por `event_type` |
| Handlers | `app/MessageHandler/` | Messenger → `NotificationDispatchService` |
| Dispatch + inbox | `app/Services/`, `app/Repositories/`, `app/Models/InboxEvent.php` | Enqueue HTTP, orquestación del worker e idempotencia |
| Canales | `app/Channels/` | Contrato `NotificationChannel`; email real; push/SMS stub |
| Email | `app/Channels/Email/` | Catálogo, Blade, adapters |
| Messenger | `app/Messenger/` | Bus, serializer JSON, worker, topología AMQP |
| Config | `config/notifications.php`, `messenger.php`, `email.php`, `notification_templates.php`, `sivacrim_notification_templates.php` | TTL, retries, from, catálogo |
| Consola | `app/Console/Commands/` | Índices, setup, consume |
| Enums / excepciones | `app/Enums/`, `app/Exceptions/` | Canal, estado de inbox, permanente vs transitorio |

## HTTP

| Clase | Responsabilidad |
|-------|-----------------|
| `AuthenticateApiKey` | Header `X-API-Key` vs `notifications.api_key` |
| `EmailController` | `POST /emails` (202 `received`) y `POST /emails/{eventId}/retry`; 503 si falla el publish |
| `NotificationController` | `GET /notifications/{eventId}` |
| `TemplateController` | `GET /templates?channel=` |
| `HealthController` | `GET /health`: ping Mongo + RabbitMQ |
| `StoreEmailRequest` | Validación XOR template/content; `toMessage()`; strip de `provider`/`from` |

Rutas: [`routes/api.php`](../routes/api.php). No hay `POST /push` ni `POST /sms` en v1.

## Mensajes y handlers

Envelope común: `event_id`, `event_type`, `occurred_at`, `idempotency_key`, `payload` ([`NotificationMessage`](../app/Message/NotificationMessage.php)).

| DTO | `event_type` | Routing key |
|-----|--------------|-------------|
| `SendEmailMessage` | `email.send.requested` | `email.send` |
| `SendPushMessage` | `push.send.requested` | `push.send` |
| `SendSmsMessage` | `sms.send.requested` | `sms.send` |
| `UnsupportedNotificationMessage` | desconocido / inválido | — |

Cada `Send*MessageHandler` solo llama a `NotificationDispatchService::dispatch`. `UnsupportedNotificationMessageHandler` lanza permanente con el `reason` del decode.

## Dispatch e inbox

| Clase | Responsabilidad |
|-------|-----------------|
| `NotificationEnqueueService` | persist inbox → publicar con `MessengerFactory::send` si no es terminal / failed permanente |
| `NotificationDispatchService` | persist → claim → render → send; tope de intentos |
| `InboxEventRepository` | `persistNew`, `claim`, `storeRendered`, `markSent` / `markFailed`, índices, retry manual |
| `InboxPersistResult` | evento + `wasInserted` / duplicado |
| `InboxEvent` | Documento Mongo `inbox_events` |
| `InboxStatus` | `received` \| `processing` \| `sent` \| `failed` \| `skipped_duplicate` |
| `NotificationChannel` (enum) | `email` \| `push` \| `sms` + `eventType()` / `routingKey()` |

## Canales

Contrato [`NotificationChannel`](../app/Channels/Contracts/NotificationChannel.php): `render()`, `send()`, `supported()`.

[`ChannelRegistry`](../app/Channels/ChannelRegistry.php) se registra como singleton en [`AppServiceProvider`](../app/Providers/AppServiceProvider.php): email, push, sms.

| Canal | Clase | `supported()` |
|-------|-------|----------------|
| email | `EmailChannel` | `true` |
| push | `PushChannel` | `false` |
| sms | `SmsChannel` | `false` |

DTOs compartidos: `RenderedNotification` (contenido + metadata de plantilla/from), `ProviderResult` (`provider`, `messageId`).

## Email

| Clase | Responsabilidad |
|-------|-----------------|
| `EmailContentResolver` | XOR template/content → `RenderedNotification`; normaliza direcciones |
| `TemplateCatalog` | Lookup en `config/notification_templates.php` |
| `TemplateRenderer` | Markdown de Laravel (`<x-mail::message>`) + interpolación `{param}` en subject |
| `InlineImageResolver` | CID en el HTML → archivos de `config/email.php` `inline_images` |
| `SymfonyEmailFactory` | `RenderedEmail` → `Symfony\Component\Mime\Email` (incluye embeds) |
| `MailProviderResolver` | `mail.default` → adapter; wrap de failover |
| `RenderedEmail` | DTO que reciben los adapters |
| `MailProviderInterface` | `name()` + `send()` |
| `LaravelMailAdapter` | Base Symfony Email + `Mail::mailer()`; errores de transporte → transitorio |
| `LogMailAdapter` / `SmtpMailAdapter` / `MailgunMailAdapter` | Especializaciones del anterior |
| `GmailMailAdapter` | Gmail API (service account + usuario delegado) |
| `FailoverMailAdapter` | Primario; fallback solo si el error es transitorio |

Vistas: `resources/views/notifications/email/{nombre}/v{n}.blade.php` (`<x-mail::message>`, + opcional `.text.blade.php`). Tema `sivacrim` en `resources/views/vendor/mail/html/themes/sivacrim.css` (texto centrado). Logos CID en `resources/images/sivacrim/`. Identidades `noreply` y `notificaciones` en `config/email.php`.

## Messenger

| Clase | Responsabilidad |
|-------|-----------------|
| `MessengerFactory` | Bus de consumo, `send()` al transporte AMQP, worker, retries, DLQ, `setupTopology()` |
| `JsonMessageSerializer` | Encode/decode JSON por `event_type` |
| `SimpleServiceLocator` | PSR-11 mínimo para listeners de Messenger |

## Consola

| Comando | Clase |
|---------|-------|
| `inbox:ensure-indexes` | `EnsureInboxIndexesCommand` |
| `messenger:setup` | `MessengerSetupCommand` |
| `messenger:consume {transport}` | `MessengerConsumeCommand` — solo si `consume: true` |

## Config relevante

| Archivo | Qué controla |
|---------|----------------|
| `config/notifications.php` | API key, claim TTL, max intentos |
| `config/messenger.php` | DSN, exchange, colas, DLQ, retries, `consume` por transporte |
| `config/email.php` | Failover, `from_identities`, credenciales Gmail, logos CID |
| `config/notification_templates.php` | Catálogo email (`welcome`, `password-reset`) + merge de plantillas SIVACRIM; `push`/`sms` vacíos |
| `config/sivacrim_notification_templates.php` | Plantillas email de SIVACRIM (`sivacrim-login-code`, `sivacrim-email-validation`, `sivacrim-password-reset`) |
| `config/mail.php` | `mail.default`, transportes Laravel y tema Markdown `sivacrim` |

`config/email.php` aún duplica api key / TTL / max attempts (aliases `EMAIL_*`); el dispatch lee `config/notifications.php`.

## Excepciones

| Clase | Efecto |
|-------|--------|
| `PermanentNotificationException` | Sin retry Messenger; inbox no retryable |
| `TransientNotificationException` | Retry Messenger; inbox retryable |
| `ChannelNotEnabledException` | Extiende permanente (canal stub) |

## Fuera del flujo de notificaciones

No participan en envío, inbox ni Messenger:

- `app/Models/User.php` y `database/factories/UserFactory.php`
- migraciones scaffold (`users`, `cache`, `jobs`) y SQLite
- `routes/web.php` / vista `welcome.blade.php`
- `app/Http/Controllers/Controller.php` (base vacía)

# Diseño

Decisiones que dan forma al microservicio. Cada apartado: contexto, decisión y consecuencia. El *cómo* está en [architecture.md](architecture.md); el contrato de payloads y API, en el [README](../README.md).

## El productor describe qué, no con qué

**Contexto.** Varios servicios van a pedir emails. Si cada uno elige SMTP, Mailgun o Gmail, el failover y el `from` se dispersan.

**Decisión.** El contrato **no incluye `provider` ni `from`**. Este servicio resuelve el mailer (`MAIL_MAILER`, failover opcional) y las identidades (`config/email.php` → `from_identities`, más `from_identity` de la plantilla). [`StoreEmailRequest::toMessage()`](../app/Http/Requests/StoreEmailRequest.php) elimina `payload.provider` y `payload.from` si vienen.

**Consecuencia.** Cambiar de proveedor es un cambio de config, no de productores. Un productor no puede forzar un remitente arbitrario.

## Inbox en MongoDB; la cola es solo transporte

**Contexto.** El mismo evento puede llegar por HTTP y, en modo `rabbitmq`, por AMQP, más de una vez (at-least-once). Hace falta un registro durable, claim entre workers y consulta de estado por `event_id`.

**Decisión.** Colección `inbox_events` con:

- índice único `event_id`
- índice único sparse `(channel, idempotency_key)` — la clave es por canal, no global
- claim atómico (`findOneAndUpdate`) con TTL (`NOTIFICATION_CLAIM_TTL`)
- tope de intentos (`NOTIFICATION_MAX_SEND_ATTEMPTS`)

El envío lo dispara el driver de cola (`NOTIFICATION_QUEUE_DRIVER`): Messenger/AMQP o un job Laravel fino (`SendNotificationJob` con `event_id`). El job **no** sustituye al inbox: desaparece al completar; el historial y la idempotencia siguen en Mongo.

**Consecuencia.** HTTP y el worker (Messenger o `queue:work`) comparten la misma idempotencia. Un duplicado de `event_id` o de `(channel, idempotency_key)` no reenvía si el documento ya está en estado terminal o `failed` no retryable. La misma `idempotency_key` sí puede usarse en email y en SMS.

## Symfony Messenger y JSON interoperable

**Contexto.** Los productores no son todos PHP. El formato nativo de Symfony Messenger no sirve fuera de PHP.

**Decisión.** Symfony Messenger + transporte AMQP, con [`JsonMessageSerializer`](../app/Messenger/JsonMessageSerializer.php): envelope `event_id`, `event_type`, `occurred_at`, `idempotency_key`, `payload`. Header `type` = `event_type`. Un `event_type` desconocido o un payload inválido se mapea a `UnsupportedNotificationMessage` (permanente), no a un fallo de decode que reintente para siempre.

**Consecuencia.** Cualquier lenguaje puede publicar al exchange. El worker de email es el único consumidor v1; push/SMS declaran cola para no romper a los productores que ya publiquen.

## HTTP persiste y publica; el worker envía

**Contexto.** Hace falta una API interna (`POST /api/emails` → 202) y, en algunos despliegues, un bus para microservicios. Enviar SMTP en el request HTTP retrasa a todos los productores.

**Decisión.** La API persiste el inbox como `received` y publica con `NotificationQueue::publish()`. No llama a `NotificationDispatchService` en el request.

- `rabbitmq`: publica el DTO con `MessengerFactory::send()`; quien envía es `messenger:consume email`. Productores externos pueden publicar JSON al exchange.
- `laravel`: despacha `SendNotificationJob` a la cola del canal (`email.send`, …); quien envía es `queue:work --queue=email.send`. No hay ingestión AMQP.

Si el evento ya está `sent` o `failed` no retryable, no se vuelve a publicar. Si el publish falla después de persistir, la API responde 503 (sin fallback síncrono).

**Consecuencia.** Un cliente HTTP obtiene `202` con `status: received` de inmediato; el inbox queda consultable. El envío (y los fallos de plantilla) ocurren en el worker. En modo `rabbitmq`, HTTP y productores AMQP nativos convergen en la misma cola y el mismo núcleo. En modo `laravel`, los productores deben usar la API.

## Permanentes vs transitorios

**Contexto.** Reintentar un template inexistente no arregla nada. Un timeout de SMTP sí.

**Decisión.**

| Tipo | Clase de dominio | Borde Messenger | Inbox |
|------|------------------|-----------------|-------|
| Permanente | `PermanentNotificationException` | `UnrecoverableNotificationException` | `failed`, `retryable: false`; no retry de Messenger |
| Transitorio | `TransientNotificationException` | `RecoverableNotificationException` | `failed`, `retryable: true`; se relanza para retry/DLQ |

Cualquier otro `Throwable` en dispatch se trata como transitorio para el inbox y se relanza. `ChannelNotEnabledException` extiende permanente.

Permanentes típicos: XOR de template/content, emails inválidos, params de plantilla faltantes, mailer no soportado, Gmail mal configurado, `event_type` desconocido. Transitorios típicos: fallo de transporte SMTP/Mailgun/Gmail.

**Consecuencia.** Las DLQ no se llenan de errores de contrato. Un fallo de red sí reintenta (hasta `MESSENGER_MAX_RETRIES` y el tope del inbox).

## Canales como registry, push/SMS como stubs

**Contexto.** El contrato de los tres canales debe existir ya para que los productores no esperen a v2.

**Decisión.** Interfaz [`NotificationChannel`](../app/Channels/Contracts/NotificationChannel.php) (`render`, `send`, `supported`). [`ChannelRegistry`](../app/Channels/ChannelRegistry.php) registra los tres. Email `supported() = true`. Push/SMS `supported() = false` y `consume: false` en Messenger.

**Consecuencia.** Activar un canal es: implementar send real, `supported() = true`, `consume: true`, catálogo de plantillas. El inbox y el envelope ya están listos. Hasta entonces, un `push.send` / `sms.send` que caiga en el worker de email falla permanente.

## Template XOR content, versión persistida

**Contexto.** La mayoría de avisos son plantillas versionadas; a veces hace falta HTML crudo.

**Decisión.** Exactamente uno de `template` o `content`. Sin `version` se usa `latest` de [`config/notification_templates.php`](../config/notification_templates.php). La versión y el `from` resueltos se guardan en el inbox (`resolved_template`, `resolved_version`, `resolved_from`). El render se reutiliza en reintentos si `hasResolvedContent()` ya es true.

**Consecuencia.** Un reintento no vuelve a interpolar una plantilla que pudo cambiar. El contenido crudo no pasa por Blade.

## Failover de mailer solo ante transitorios

**Contexto.** SMTP puede caer; un `from` o una plantilla malos no se arreglan cambiando de proveedor.

**Decisión.** Si `MAIL_FAILOVER_MAILER` ≠ mailer primario, [`FailoverMailAdapter`](../app/Channels/Email/Adapters/FailoverMailAdapter.php) envuelve ambos. Permanente del primario se propaga. Transitorio del primario intenta el fallback; si el fallback también es transitorio, se relanza el error **del primario**. El `provider` reportado en éxito de fallback es `{primario}+{fallback}`.

**Consecuencia.** El failover no enmascara errores de contrato. El inbox deja constancia de qué cadena de proveedores funcionó.

## `skipped_duplicate` no se usa en el camino vivo

El enum y `InboxEventRepository::markSkippedDuplicate()` existen. El dispatch de producción **no** llama a ese método: un duplicado ya `sent` sale por `isTerminal()`; un duplicado `failed` no retryable sale sin claim. El estado queda cubierto por tests, no por el flujo HTTP/worker actual.

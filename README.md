# Microservicio de notificaciones

Servicio Laravel 13 (API) para enviar notificaciones. **v1 envía solo email**. Push y SMS tienen contrato, colas e inbox listos; no hay workers ni envío real de esos canales.

El productor describe *qué* enviar. Este servicio decide *con qué proveedor* (SMTP, Mailgun, Gmail o Log). El contrato **no incluye `provider` ni `from`**.

## Documentación

| Documento | Contenido |
|-----------|-----------|
| [Arquitectura](docs/architecture.md) | Entradas HTTP/RabbitMQ, inbox, Messenger, pipeline email |
| [Diseño](docs/design.md) | Decisiones: contrato, idempotencia, permanentes vs transitorios |
| [Componentes](docs/components.md) | Mapa de clases por capa |
| [Testing](docs/testing.md) | Pest, cómo correr tests, mapa de suites |

## Requisitos

- PHP 8.3+ con `ext-mongodb`; `ext-amqp` y `ext-pcntl` si `NOTIFICATION_QUEUE_DRIVER=rabbitmq`
- MongoDB (`docker compose up -d`); RabbitMQ solo en modo `rabbitmq`

```bash
cp .env.example .env
php artisan key:generate
docker compose up -d
php artisan inbox:ensure-indexes
```

Cola: `NOTIFICATION_QUEUE_DRIVER=rabbitmq` (default) o `laravel`.

**RabbitMQ** (pub/sub: API HTTP y mensajes AMQP):

```bash
php artisan messenger:setup
php artisan messenger:consume email --time-limit=3600
```

**Laravel Queue** (solo API HTTP; no se consumen eventos de RabbitMQ):

```bash
# QUEUE_CONNECTION=database o redis (no sync en producción)
php artisan queue:work --queue=email.send
# escala: más procesos/réplicas del mismo comando
```

Cuando se activen canales: `--queue=push.send` y `--queue=sms.send` en procesos separados.

## RabbitMQ

Aplica cuando `NOTIFICATION_QUEUE_DRIVER=rabbitmq`. Exchange topic: `MESSENGER_EXCHANGE` (en `.env.example`: `notificaciones`). Si la variable no está, el fallback de `config/messenger.php` es `notifications`.

| Canal | Routing key | Cola | Worker v1 |
|-------|-------------|------|-----------|
| email | `email.send` | `email.send` | sí |
| push | `push.send` | `push.send` | no (cola declarada) |
| sms | `sms.send` | `sms.send` | no (cola declarada) |

DLQ: `email.send.dlq`, `push.send.dlq`, `sms.send.dlq`.

Sobre común:

```json
{
  "event_id": "550e8400-e29b-41d4-a716-446655440000",
  "event_type": "email.send.requested",
  "occurred_at": "2026-08-25T13:37:00Z",
  "idempotency_key": "case-123:welcome",
  "payload": {}
}
```

`event_type`: `email.send.requested` | `push.send.requested` | `sms.send.requested`.

El serializer es JSON interoperable (no el formato PHP de Symfony).

### Payload email (v1)

Exactamente uno de `template` o `content`.

**Plantilla**

```json
{
  "event_id": "550e8400-e29b-41d4-a716-446655440000",
  "event_type": "email.send.requested",
  "occurred_at": "2026-08-25T13:37:00Z",
  "idempotency_key": "case-123:welcome",
  "payload": {
    "to": [{"email": "user@example.com", "name": "Usuario"}],
    "cc": [],
    "bcc": [],
    "reply_to": null,
    "template": {
      "name": "welcome",
      "version": 1,
      "params": { "name": "Juan" }
    }
  }
}
```

Sin `version` se usa `latest` del catálogo. La versión resuelta se persiste en el inbox.

**Contenido crudo**

```json
{
  "event_id": "550e8400-e29b-41d4-a716-446655440000",
  "event_type": "email.send.requested",
  "idempotency_key": "case-123:custom",
  "payload": {
    "to": [{"email": "user@example.com", "name": "Usuario"}],
    "content": {
      "subject": "Asunto",
      "html": "<p>Cuerpo</p>",
      "text": "Cuerpo"
    }
  }
}
```

### Payload push (contrato; sin envío)

```json
{
  "event_id": "...",
  "event_type": "push.send.requested",
  "idempotency_key": "case-123:welcome",
  "payload": {
    "tokens": ["fcm-or-apns-token"],
    "template": { "name": "welcome", "version": 1, "params": { "name": "Juan" } }
  }
}
```

o `content`: `{ "title": "...", "body": "...", "data": {} }`.

### Payload SMS (contrato; sin envío)

```json
{
  "event_id": "...",
  "event_type": "sms.send.requested",
  "idempotency_key": "case-123:otp",
  "payload": {
    "to": ["+580000000000"],
    "template": { "name": "otp", "version": 1, "params": { "code": "1234" } }
  }
}
```

o `content`: `{ "text": "..." }`.

Si un `push.send` / `sms.send` llega al worker de email, se rechaza como error permanente.

## API REST

Auth: header `X-API-Key`.

| Método | Ruta | Descripción |
|--------|------|-------------|
| POST | `/api/emails` | Encola el email (mismo contrato). `202` con `status: received`. |
| GET | `/api/notifications/{eventId}` | Estado (channel, status, provider resuelto). |
| POST | `/api/emails/{eventId}/retry` | Reintento manual si `failed`. |
| GET | `/api/templates?channel=email` | Catálogo por canal. |
| GET | `/api/health` | App, MongoDB y RabbitMQ o cola Laravel (sin API key). |

No hay `POST /api/push` ni `POST /api/sms` en v1.

## Inbox (MongoDB)

Colección `inbox_events`:

- Índice único `event_id`
- Índice único sparse `(channel, idempotency_key)` — la misma clave puede usarse en email y SMS
- Claim atómico (`findOneAndUpdate`) con TTL
- Campo `channel`: `email` | `push` | `sms`
- `rendered` genérico: email `{ subject, html, text }`

Estados: `received` → `processing` → `sent` | `failed` | `skipped_duplicate`.

## Plantillas

Vistas en `resources/views/notifications/email/{nombre}/v{n}.blade.php`, envueltas en `<x-mail::message>`. Tema Markdown `sivacrim` (`resources/views/vendor/mail/html/themes/sivacrim.css`): layout Laravel con texto centrado, como los correos originales de SIVACRIM. Catálogo: `config/notification_templates.php`. Plantillas SIVACRIM: `config/sivacrim_notification_templates.php` (se fusionan en el catálogo email). Partials de SIVACRIM versionados en `resources/views/notifications/email/sivacrim-partials/{header|footer}/v{n}.blade.php`. Los logos de SIVACRIM se incrustan como CID (`resources/images/sivacrim/`), no como URL del productor.

## Proveedores de email

`MAIL_MAILER`: `log` (local/testing), `smtp`, `mailgun`, `gmail`. Failover opcional: `MAIL_FAILOVER_MAILER`.

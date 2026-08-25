# Microservicio de notificaciones

Servicio Laravel 13 (API) para enviar notificaciones. **v1 envía solo email**. Push y SMS tienen contrato, colas e inbox listos; no hay workers ni envío real de esos canales.

El productor describe *qué* enviar. Este servicio decide *con qué proveedor* (SMTP, Mailgun, Gmail o Log). El contrato **no incluye `provider` ni `from`**.

## Requisitos

- PHP 8.3+ con `ext-mongodb`, `ext-amqp` y `ext-pcntl`
- MongoDB y RabbitMQ (`docker compose up -d`)

```bash
cp .env.example .env
php artisan key:generate
docker compose up -d
php artisan inbox:ensure-indexes
php artisan messenger:setup
```

Worker de email:

```bash
php artisan messenger:consume email --time-limit=3600
```

## RabbitMQ

Exchange topic: `notificaciones`.

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
| POST | `/api/emails` | Disparo email (mismo contrato). `202`. |
| GET | `/api/notifications/{eventId}` | Estado (channel, status, provider resuelto). |
| POST | `/api/emails/{eventId}/retry` | Reintento manual si `failed`. |
| GET | `/api/templates?channel=email` | Catálogo por canal. |
| GET | `/api/health` | App, MongoDB, RabbitMQ (sin API key). |

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

Vistas en `resources/views/notifications/email/{nombre}/v{n}.blade.php`. Catálogo: `config/notification_templates.php`.

## Proveedores de email

`MAIL_MAILER`: `log` (local/testing), `smtp`, `mailgun`, `gmail`. Failover opcional: `MAIL_FAILOVER_MAILER`.

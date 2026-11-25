# Testing

Pest 4 con el plugin Laravel. Suites **Unit** y **Feature** en [`phpunit.xml`](../phpunit.xml). Coverage sobre `app/` (se excluye `app/Models/User.php`). Umbral: `composer test:coverage` → `--min=83`.

Arquitectura bajo prueba: [architecture.md](architecture.md). Componentes: [components.md](components.md).

## Cómo correrlos

```bash
composer test
# equivalente
php artisan test
```

Coverage (requiere Xdebug o PCOV):

```bash
composer test:coverage
```

Entorno de test (`phpunit.xml`): `APP_ENV=testing`, `MAIL_MAILER=log`, `NOTIFICATIONS_API_KEY=testing-key`, Mongo `laravel_email_test`, SQLite in-memory (no usado por el inbox).

### Dependencias

| Qué | Cuándo hace falta |
|-----|-------------------|
| MongoDB en `127.0.0.1:27017` | Feature de inbox/API/dispatch (`docker compose up -d`) |
| RabbitMQ | Casi nunca: los comandos Messenger se mockean; health puede mockear AMQP |
| SMTP / Mailgun / Gmail reales | Nunca: el mailer de test es `log` |

Si Mongo no responde, [`InteractsWithMongoInbox`](../tests/Concerns/InteractsWithMongoInbox.php) hace `markTestSkipped` (ping a `admin`). Si está, trunca `inbox_events` y recrea índices.

## Bootstrap

- [`tests/Pest.php`](../tests/Pest.php): Feature y Unit extienden `Tests\TestCase`. Helpers `makeRenderedEmail()` y `fakeNotificationQueue()`.
- [`tests/TestCase.php`](../tests/TestCase.php): `Illuminate\Foundation\Testing\TestCase` (boot estándar de Laravel).
- Trait `InteractsWithMongoInbox`: `setUpMongoInbox()` en los Feature que tocan la colección.

## Mapa de suites

No se lista cada `it()`. Cada archivo cubre un recorte del sistema.

### Feature (`tests/Feature/`)

| Archivo | Qué cubre |
|---------|-----------|
| `NotificationApiTest` | `POST /api/emails` 202: plantilla, contenido crudo, fallo permanente por params; GET de estado; `from` eliminado |
| `NotificationApiPublicTest` | 401 sin API key, listado de plantillas, canal inválido 422, XOR template/content, health 200/503 por driver |
| `NotificationDispatchServiceTest` | Duplicado no retryable, canales push/sms deshabilitados, transitorio que relanza, race de claim, tope de intentos, `skipped_duplicate` |
| `InboxIdempotencyTest` | Mismo `event_id`; misma `idempotency_key` en un canal; la misma clave sí en otro canal |
| `EmailRetryTest` | 404, 422 si no es email, 409 si ya `sent`, retry de `failed` → `sent`; `inbox:ensure-indexes` |
| `ConsoleCommandsTest` | Transporte desconocido, `consume` de push en v1, `messenger:setup`/`consume` con factory mockeados; rechazo si driver `laravel` |
| `NotificationEnqueueServiceTest` | Persist + publish; skip de terminal/permanente; republish de received; job Laravel en `email.send` si el driver es `laravel` |

### Unit (`tests/Unit/`)

| Archivo | Qué cubre |
|---------|-----------|
| `JsonMessageSerializerTest` | Round-trip email, mapeo push/sms, `event_type` desconocido/ausente, JSON inválido, encode |
| `MessageHandlersTest` | Handlers delegan a dispatch; unsupported → permanente; transitorio → Recoverable Messenger |
| `NotificationQueueTest` | Publish AMQP vs dispatch de `SendNotificationJob` a `email.send` / `push.send` / `sms.send` |
| `SendNotificationJobTest` | Carga inbox y dispatch; no-op si falta el evento; backoff |
| `QueueDriverTest` | `rabbitmq` / `laravel` / valor inválido |
| `MessengerExceptionMappingTest` | Excepciones de dominio sin interfaces Messenger; wrappers del borde AMQP |
| `NotificationMessageTest` | Canal/event type, `fromInbox`, defaults, `event_id` obligatorio |
| `EmailContentResolverTest` | Welcome Markdown, contenido crudo, XOR, params, versiones, destinatarios, normalización de strings, layout Laravel y CID SIVACRIM |
| `TemplateCatalogTest` | Catálogo, vista faltante, subject con placeholders |
| `InlineImageResolverTest` | CID presentes, ausentes o archivo faltante |
| `SymfonyEmailFactoryTest` | Destinatarios, reply-to y embed de logos |
| `MailProviderResolverTest` | smtp/sendmail/mailgun/gmail/log/array; rechazo de mailer desconocido; wrap de failover |
| `LaravelMailAdapterTest` | Envío vía `log` (cc/bcc/reply-to); excepción de Mail → transitorio |
| `FailoverMailAdapterTest` | Éxito primario; permanente no cae; transitorio sí; ambos transitorios relanzan el del primario |
| `GmailMailAdapterTest` | Config ausente/inválida → permanente; credenciales de archivo malas → transitorio |
| `ChannelRegistryTest` | Canal registrado vs desconocido |
| `StubChannelsTest` | Push/SMS `supported() = false` y excepción al render/send |
| `NotificationChannelTest` | `eventType` / `routingKey` del enum |
| `InboxStatusTest` | Terminales, reglas de claim, `hasResolvedContent` por canal |
| `SimpleServiceLocatorTest` | `has`/`get` y not found |

## Cómo añadir un test

1. **Feature** si toca HTTP, inbox Mongo o `NotificationDispatchService` de punta a punta. Llama `setUpMongoInbox()` (via `beforeEach` o el patrón del archivo vecino).
2. **Unit** si es una clase aislada (serializer, resolver, adapter, registry).
3. Reutiliza `makeRenderedEmail()` y los helpers locales del archivo (`dispatchEmail`, payloads, etc.) en lugar de duplicar envelopes.
4. Mailer: deja `MAIL_MAILER=log`. No apuntes tests a SMTP real.
5. Cola: `fakeNotificationQueue()` en API/enqueue; mockea `MessengerFactory` / `Worker` en comandos Messenger. No levantes un consumidor AMQP.
6. Auth HTTP: header `X-API-Key: testing-key` (valor de `phpunit.xml`).
7. Tras añadir código en `app/`, `composer test:coverage` debe seguir ≥ 83 %.

## Huecos conscientes

No hay, a propósito:

- Envío real por SMTP, Mailgun o Gmail (Gmail se valida por configuración, no contra la API).
- Worker AMQP de extremo a extremo (publicar a RabbitMQ y consumir).
- CI (no hay `.github/workflows`).
- Tests de `User`, migraciones scaffold o `routes/web.php`.

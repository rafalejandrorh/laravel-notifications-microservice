<?php

namespace App\Messenger;

use App\Message\SendEmailMessage;
use App\Message\SendPushMessage;
use App\Message\SendSmsMessage;
use App\Message\UnsupportedNotificationMessage;
use App\MessageHandler\SendEmailMessageHandler;
use App\MessageHandler\SendPushMessageHandler;
use App\MessageHandler\SendSmsMessageHandler;
use App\MessageHandler\UnsupportedNotificationMessageHandler;
use Illuminate\Support\Facades\Log;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransport;
use Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpTransportFactory;
use Symfony\Component\Messenger\EventListener\AddErrorDetailsStampListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageForRetryListener;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\Handler\HandlersLocator;
use Symfony\Component\Messenger\MessageBus;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Middleware\HandleMessageMiddleware;
use Symfony\Component\Messenger\Retry\MultiplierRetryStrategy;
use Symfony\Component\Messenger\Worker;

class MessengerFactory
{
    /**
     * @var array<string, AmqpTransport>
     */
    private array $transports = [];

    /**
     * @var array<string, AmqpTransport>
     */
    private array $failureTransports = [];

    public function __construct(
        private JsonMessageSerializer $serializer,
        private SendEmailMessageHandler $emailHandler,
        private SendPushMessageHandler $pushHandler,
        private SendSmsMessageHandler $smsHandler,
        private UnsupportedNotificationMessageHandler $unsupportedHandler,
    ) {}

    public function bus(): MessageBusInterface
    {
        return new MessageBus([
            new HandleMessageMiddleware(new HandlersLocator([
                SendEmailMessage::class => [$this->emailHandler],
                SendPushMessage::class => [$this->pushHandler],
                SendSmsMessage::class => [$this->smsHandler],
                UnsupportedNotificationMessage::class => [$this->unsupportedHandler],
            ])),
        ]);
    }

    public function transport(string $name, bool $failure = false): AmqpTransport
    {
        $cache = $failure ? $this->failureTransports : $this->transports;

        if (isset($cache[$name])) {
            return $cache[$name];
        }

        $config = config("messenger.transports.{$name}");

        if (! is_array($config)) {
            throw new \InvalidArgumentException("Transporte Messenger [{$name}] no configurado.");
        }

        $queue = $failure ? $config['failure_queue'] : $config['queue'];
        $routingKey = $failure ? $config['failure_queue'] : $config['routing_key'];
        $dsn = $failure
            ? (string) config('messenger.failure_dsn')
            : (string) config('messenger.dsn');

        $transport = (new AmqpTransportFactory)->createTransport($dsn, [
            'exchange' => [
                'name' => (string) config('messenger.exchange'),
                'type' => 'topic',
                'default_publish_routing_key' => $routingKey,
            ],
            'queues' => [
                $queue => [
                    'binding_keys' => [$routingKey],
                ],
            ],
            'auto_setup' => true,
            'connect_timeout' => 2,
            'read_timeout' => 2,
            'write_timeout' => 2,
        ], $this->serializer);

        if ($failure) {
            $this->failureTransports[$name] = $transport;
        } else {
            $this->transports[$name] = $transport;
        }

        return $transport;
    }

    public function setupTopology(): void
    {
        foreach (array_keys(config('messenger.transports', [])) as $name) {
            $this->transport($name)->setup();
            $this->transport($name, true)->setup();
        }
    }

    /**
     * @return array<string, AmqpTransport>
     */
    public function receiversFor(string $name): array
    {
        return [$name => $this->transport($name)];
    }

    public function worker(string $transportName, ?int $messageLimit = null): Worker
    {
        $retry = config('messenger.retry');
        $strategy = new MultiplierRetryStrategy(
            (int) $retry['max_retries'],
            (int) $retry['delay_ms'],
            (float) $retry['multiplier'],
            (int) $retry['max_delay_ms'],
        );

        $senders = new SimpleServiceLocator([
            $transportName => $this->transport($transportName),
        ]);
        $retries = new SimpleServiceLocator([
            $transportName => $strategy,
        ]);
        $failures = new SimpleServiceLocator([
            $transportName => $this->transport($transportName, true),
        ]);

        $dispatcher = new EventDispatcher;
        $dispatcher->addSubscriber(new AddErrorDetailsStampListener);
        $dispatcher->addSubscriber(new SendFailedMessageForRetryListener(
            $senders,
            $retries,
            Log::channel(),
        ));
        $dispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener(
            $failures,
            Log::channel(),
        ));

        if ($messageLimit !== null && $messageLimit > 0) {
            $dispatcher->addSubscriber(new StopWorkerOnMessageLimitListener($messageLimit));
        }

        return new Worker(
            $this->receiversFor($transportName),
            $this->bus(),
            $dispatcher,
            Log::channel(),
        );
    }
}

<?php

namespace App\Providers;

use App\Channels\ChannelRegistry;
use App\Channels\Email\EmailChannel;
use App\Channels\Push\PushChannel;
use App\Channels\Sms\SmsChannel;
use App\Contracts\NotificationQueue;
use App\Enums\NotificationChannel;
use App\Enums\QueueDriver;
use App\Queue\LaravelNotificationQueue;
use App\Queue\RabbitMqNotificationQueue;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ChannelRegistry::class, function ($app): ChannelRegistry {
            return new ChannelRegistry([
                NotificationChannel::Email->value => $app->make(EmailChannel::class),
                NotificationChannel::Push->value => $app->make(PushChannel::class),
                NotificationChannel::Sms->value => $app->make(SmsChannel::class),
            ]);
        });

        $this->app->singleton(NotificationQueue::class, function ($app): NotificationQueue {
            return match (QueueDriver::current()) {
                QueueDriver::Laravel => $app->make(LaravelNotificationQueue::class),
                QueueDriver::RabbitMq => $app->make(RabbitMqNotificationQueue::class),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}

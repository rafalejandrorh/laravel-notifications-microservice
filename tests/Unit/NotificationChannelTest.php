<?php

namespace Tests\Unit;

use App\Enums\NotificationChannel;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class NotificationChannelTest extends TestCase
{
    #[Test]
    public function it_exposes_event_type_and_routing_key_per_channel(): void
    {
        $this->assertSame('email.send.requested', NotificationChannel::Email->eventType());
        $this->assertSame('email.send', NotificationChannel::Email->routingKey());
        $this->assertSame('push.send.requested', NotificationChannel::Push->eventType());
        $this->assertSame('sms.send', NotificationChannel::Sms->routingKey());
    }
}

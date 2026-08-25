<?php

namespace Tests\Unit;

use App\Enums\InboxStatus;
use App\Enums\NotificationChannel;
use App\Models\InboxEvent;
use App\Repositories\InboxPersistResult;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InboxStatusTest extends TestCase
{
    #[Test]
    public function sent_and_skipped_are_terminal(): void
    {
        $this->assertTrue(InboxStatus::Sent->isTerminal());
        $this->assertTrue(InboxStatus::SkippedDuplicate->isTerminal());
        $this->assertFalse(InboxStatus::Received->isTerminal());
        $this->assertFalse(InboxStatus::Processing->isTerminal());
        $this->assertFalse(InboxStatus::Failed->isTerminal());
    }

    #[Test]
    public function received_and_retryable_failed_events_can_be_claimed(): void
    {
        $received = new InboxEvent(['status' => InboxStatus::Received]);
        $failedRetryable = new InboxEvent(['status' => InboxStatus::Failed, 'retryable' => true]);
        $failedPermanent = new InboxEvent(['status' => InboxStatus::Failed, 'retryable' => false]);
        $sent = new InboxEvent(['status' => InboxStatus::Sent]);

        $this->assertTrue($received->canBeClaimed());
        $this->assertTrue($failedRetryable->canBeClaimed());
        $this->assertFalse($failedPermanent->canBeClaimed());
        $this->assertFalse($sent->canBeClaimed());
    }

    #[Test]
    public function persist_result_reports_duplicates(): void
    {
        $event = new InboxEvent(['event_id' => 'abc']);

        $this->assertFalse((new InboxPersistResult($event, true))->wasDuplicate());
        $this->assertTrue((new InboxPersistResult($event, false))->wasDuplicate());
    }

    #[Test]
    public function email_resolved_content_uses_the_rendered_document(): void
    {
        $event = new InboxEvent([
            'channel' => NotificationChannel::Email,
            'rendered' => ['subject' => 'Hola', 'html' => '<p>x</p>'],
        ]);

        $this->assertTrue($event->hasResolvedContent());

        $event->rendered = ['subject' => 'Hola'];
        $this->assertFalse($event->hasResolvedContent());
    }
}

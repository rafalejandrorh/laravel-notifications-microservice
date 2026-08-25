<?php

use App\Enums\InboxStatus;
use App\Enums\NotificationChannel;
use App\Models\InboxEvent;
use App\Repositories\InboxPersistResult;

it('marks sent and skipped as terminal', function () {
    expect(InboxStatus::Sent->isTerminal())->toBeTrue();
    expect(InboxStatus::SkippedDuplicate->isTerminal())->toBeTrue();
    expect(InboxStatus::Received->isTerminal())->toBeFalse();
    expect(InboxStatus::Processing->isTerminal())->toBeFalse();
    expect(InboxStatus::Failed->isTerminal())->toBeFalse();
});

it('allows claiming received and retryable failed events', function () {
    $received = new InboxEvent(['status' => InboxStatus::Received]);
    $failedRetryable = new InboxEvent(['status' => InboxStatus::Failed, 'retryable' => true]);
    $failedPermanent = new InboxEvent(['status' => InboxStatus::Failed, 'retryable' => false]);
    $sent = new InboxEvent(['status' => InboxStatus::Sent]);

    expect($received->canBeClaimed())->toBeTrue();
    expect($failedRetryable->canBeClaimed())->toBeTrue();
    expect($failedPermanent->canBeClaimed())->toBeFalse();
    expect($sent->canBeClaimed())->toBeFalse();
});

it('reports duplicates from persist result', function () {
    $event = new InboxEvent(['event_id' => 'abc']);

    expect((new InboxPersistResult($event, true))->wasDuplicate())->toBeFalse();
    expect((new InboxPersistResult($event, false))->wasDuplicate())->toBeTrue();
});

it('uses the rendered document for email resolved content', function () {
    $event = new InboxEvent([
        'channel' => NotificationChannel::Email,
        'rendered' => ['subject' => 'Hola', 'html' => '<p>x</p>'],
    ]);

    expect($event->hasResolvedContent())->toBeTrue();

    $event->rendered = ['subject' => 'Hola'];
    expect($event->hasResolvedContent())->toBeFalse();
});

it('uses title and body for push resolved content', function () {
    $event = new InboxEvent([
        'channel' => NotificationChannel::Push,
        'rendered' => ['title' => 'Alerta', 'body' => 'Detalle'],
    ]);

    expect($event->hasResolvedContent())->toBeTrue();

    $event->rendered = ['title' => 'Alerta'];
    expect($event->hasResolvedContent())->toBeFalse();
});

it('uses text for sms resolved content', function () {
    $event = new InboxEvent([
        'channel' => NotificationChannel::Sms,
        'rendered' => ['text' => 'Hola'],
    ]);

    expect($event->hasResolvedContent())->toBeTrue();

    $event->rendered = [];
    expect($event->hasResolvedContent())->toBeFalse();
});

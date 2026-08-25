<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationApiPublicTest extends TestCase
{
    #[Test]
    public function it_requires_an_api_key(): void
    {
        $this->postJson('/api/emails', [])->assertUnauthorized();
    }

    #[Test]
    public function it_lists_email_templates(): void
    {
        $this->getJson('/api/templates?channel=email', ['X-API-Key' => 'testing-key'])
            ->assertOk()
            ->assertJsonPath('channel', 'email')
            ->assertJsonFragment(['name' => 'welcome']);
    }

    #[Test]
    public function health_reports_dependency_checks(): void
    {
        $this->getJson('/api/health')
            ->assertJsonStructure(['status', 'checks' => ['app', 'mongodb', 'rabbitmq']]);
    }

    #[Test]
    public function it_rejects_both_template_and_content(): void
    {
        $this->postJson('/api/emails', [
            'payload' => [
                'to' => [['email' => 'user@example.com']],
                'template' => ['name' => 'welcome', 'params' => ['name' => 'Juan']],
                'content' => ['subject' => 'X', 'text' => 'Y'],
            ],
        ], ['X-API-Key' => 'testing-key'])
            ->assertUnprocessable();
    }
}

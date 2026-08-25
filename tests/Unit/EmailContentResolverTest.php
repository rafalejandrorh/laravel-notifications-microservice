<?php

namespace Tests\Unit;

use App\Channels\Email\EmailContentResolver;
use App\Channels\Email\TemplateCatalog;
use App\Channels\Email\TemplateRenderer;
use App\Enums\NotificationChannel;
use App\Exceptions\PermanentNotificationException;
use App\Models\InboxEvent;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EmailContentResolverTest extends TestCase
{
    private EmailContentResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new EmailContentResolver(
            new TemplateCatalog,
            new TemplateRenderer,
        );
    }

    #[Test]
    public function it_renders_welcome_template_and_uses_latest_when_version_is_omitted(): void
    {
        $rendered = $this->resolver->resolve($this->event([
            'to' => [['email' => 'user@example.com']],
            'template' => [
                'name' => 'welcome',
                'params' => ['name' => 'Juan'],
            ],
        ]));

        $this->assertSame('welcome', $rendered->templateName);
        $this->assertSame(1, $rendered->templateVersion);
        $this->assertSame('Bienvenido, Juan', $rendered->content['subject']);
        $this->assertStringContainsString('Juan', $rendered->content['html']);
    }

    #[Test]
    public function it_renders_raw_content_without_blade(): void
    {
        $rendered = $this->resolver->resolve($this->event([
            'to' => [['email' => 'user@example.com']],
            'content' => [
                'subject' => 'Asunto crudo',
                'html' => '<p>Hola</p>',
            ],
        ]));

        $this->assertNull($rendered->templateName);
        $this->assertSame('Asunto crudo', $rendered->content['subject']);
        $this->assertSame('<p>Hola</p>', $rendered->content['html']);
    }

    #[Test]
    public function it_rejects_template_and_content_together(): void
    {
        $this->expectException(PermanentNotificationException::class);

        $this->resolver->resolve($this->event([
            'to' => [['email' => 'user@example.com']],
            'template' => ['name' => 'welcome', 'params' => ['name' => 'Juan']],
            'content' => ['subject' => 'X', 'text' => 'Y'],
        ]));
    }

    #[Test]
    public function it_rejects_missing_required_params_and_unknown_versions(): void
    {
        try {
            $this->resolver->resolve($this->event([
                'to' => [['email' => 'user@example.com']],
                'template' => ['name' => 'welcome', 'params' => []],
            ]));
            $this->fail('Missing params should fail');
        } catch (PermanentNotificationException $exception) {
            $this->assertStringContainsString('name', $exception->getMessage());
        }

        $this->expectException(PermanentNotificationException::class);
        $this->resolver->resolve($this->event([
            'to' => [['email' => 'user@example.com']],
            'template' => ['name' => 'welcome', 'version' => 99, 'params' => ['name' => 'Juan']],
        ]));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function event(array $payload): InboxEvent
    {
        return new InboxEvent([
            'event_id' => '550e8400-e29b-41d4-a716-446655440000',
            'channel' => NotificationChannel::Email,
            'payload' => $payload,
        ]);
    }
}

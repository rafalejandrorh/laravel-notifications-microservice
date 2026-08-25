<?php

namespace App\Channels\Email;

use App\Enums\NotificationChannel;
use App\Exceptions\PermanentNotificationException;

class TemplateCatalog
{
    /**
     * @return array<string, mixed>
     */
    public function all(?NotificationChannel $channel = null): array
    {
        $templates = config('notification_templates', []);

        if ($channel === null) {
            return $templates;
        }

        return $templates[$channel->value] ?? [];
    }

    /**
     * @return list<array{name: string, latest: int, versions: list<int>, required_params: list<string>}>
     */
    public function list(NotificationChannel $channel): array
    {
        $listed = [];

        foreach ($this->all($channel) as $name => $definition) {
            $latest = (int) ($definition['latest'] ?? 1);
            $versionMeta = $definition['versions'][$latest] ?? [];

            $listed[] = [
                'name' => (string) $name,
                'latest' => $latest,
                'versions' => array_map('intval', array_keys($definition['versions'] ?? [])),
                'required_params' => $versionMeta['required_params'] ?? [],
            ];
        }

        return $listed;
    }

    /**
     * @return array{
     *     name: string,
     *     version: int,
     *     subject: string,
     *     required_params: list<string>,
     *     from_identity: string|null,
     *     view: string
     * }
     */
    public function resolve(NotificationChannel $channel, string $name, ?int $version): array
    {
        $definition = $this->all($channel)[$name] ?? null;

        if (! is_array($definition)) {
            throw new PermanentNotificationException("Plantilla [{$name}] no existe en el canal [{$channel->value}].");
        }

        $resolvedVersion = $version ?? (int) ($definition['latest'] ?? 1);
        $versionMeta = $definition['versions'][$resolvedVersion] ?? null;

        if (! is_array($versionMeta)) {
            throw new PermanentNotificationException("La versión [{$resolvedVersion}] de [{$name}] no existe.");
        }

        return [
            'name' => $name,
            'version' => $resolvedVersion,
            'subject' => (string) ($versionMeta['subject'] ?? ''),
            'required_params' => $versionMeta['required_params'] ?? [],
            'from_identity' => $definition['from_identity'] ?? ($versionMeta['from_identity'] ?? null),
            'view' => "notifications.{$channel->value}.{$name}.v{$resolvedVersion}",
        ];
    }
}

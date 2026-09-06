<?php

namespace App\Channels\Email;

final class InlineImageResolver
{
    /**
     * @return list<array{path: string, name: string, mime: string}>
     */
    public function resolve(?string $html): array
    {
        if (! filled($html)) {
            return [];
        }

        $images = [];

        foreach (config('email.inline_images', []) as $name => $path) {
            $path = (string) $path;

            if (! str_contains($html, 'cid:'.$name) || ! is_file($path)) {
                continue;
            }

            $images[] = [
                'path' => $path,
                'name' => (string) $name,
                'mime' => 'image/png',
            ];
        }

        return $images;
    }
}

<?php

namespace App\Channels\Email;

use App\Exceptions\PermanentNotificationException;
use Illuminate\Support\Facades\View;

class TemplateRenderer
{
    /**
     * @param  array<string, mixed>  $params
     * @return array{subject: string, html: string, text: string}
     */
    public function render(array $template, array $params): array
    {
        $missing = array_values(array_filter(
            $template['required_params'] ?? [],
            fn (string $param): bool => ! array_key_exists($param, $params) || $params[$param] === null || $params[$param] === '',
        ));

        if ($missing !== []) {
            throw new PermanentNotificationException('Faltan parámetros de plantilla: '.implode(', ', $missing).'.');
        }

        $view = $template['view'];

        if (! View::exists($view)) {
            throw new PermanentNotificationException("No existe la vista de plantilla [{$view}].");
        }

        $html = View::make($view, $params)->render();
        $textFile = resource_path('views/'.str_replace('.', '/', $view).'.text.blade.php');
        $text = is_file($textFile)
            ? View::file($textFile, $params)->render()
            : trim(html_entity_decode(strip_tags($html)));

        return [
            'subject' => $this->interpolate((string) $template['subject'], $params),
            'html' => $html,
            'text' => $text,
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function interpolate(string $subject, array $params): string
    {
        return preg_replace_callback('/\{(\w+)\}/', function (array $matches) use ($params): string {
            $value = $params[$matches[1]] ?? null;

            return $value === null ? $matches[0] : (string) $value;
        }, $subject) ?? $subject;
    }
}

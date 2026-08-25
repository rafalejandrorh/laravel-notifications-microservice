<?php

namespace App\Http\Requests;

use App\Message\SendEmailMessage;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\Validator as IlluminateValidator;

class StoreEmailRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'event_id' => ['nullable', 'uuid'],
            'event_type' => ['nullable', 'in:email.send.requested'],
            'occurred_at' => ['nullable', 'date'],
            'idempotency_key' => ['nullable', 'string', 'max:255'],
            'payload' => ['required', 'array'],
            'payload.to' => ['required', 'array', 'min:1'],
            'payload.cc' => ['nullable', 'array'],
            'payload.bcc' => ['nullable', 'array'],
            'payload.template' => ['required_without:payload.content', 'array'],
            'payload.content' => ['required_without:payload.template', 'array'],
            'payload.template.name' => ['required_with:payload.template', 'string'],
            'payload.template.version' => ['nullable', 'integer', 'min:1'],
            'payload.template.params' => ['nullable', 'array'],
            'payload.content.subject' => ['required_with:payload.content', 'string'],
            'payload.content.html' => ['nullable', 'string'],
            'payload.content.text' => ['nullable', 'string'],
        ];
    }

    public function withValidator(IlluminateValidator $validator): void
    {
        $validator->after(function (IlluminateValidator $validator): void {
            $hasTemplate = $this->has('payload.template');
            $hasContent = $this->has('payload.content');

            if ($hasTemplate && $hasContent) {
                $validator->errors()->add('payload', 'Debe enviarse template o content, no ambos.');
            }

            if ($hasContent) {
                $html = $this->input('payload.content.html');
                $text = $this->input('payload.content.text');

                if (! filled($html) && ! filled($text)) {
                    $validator->errors()->add('payload.content', 'content debe incluir html o text.');
                }
            }
        });
    }

    public function toMessage(): SendEmailMessage
    {
        $payload = $this->input('payload', []);
        unset($payload['provider'], $payload['from']);

        return SendEmailMessage::fromArray([
            'event_id' => $this->input('event_id') ?: (string) Str::uuid(),
            'event_type' => $this->input('event_type') ?: SendEmailMessage::defaultEventType(),
            'occurred_at' => $this->input('occurred_at'),
            'idempotency_key' => $this->input('idempotency_key'),
            'payload' => $payload,
        ]);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'message' => 'The given data was invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}

<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppMessagingService
{
    private string $accessToken;
    private string $phoneNumberId;
    private string $apiVersion;

    public function __construct()
    {
        $this->accessToken = config('services.meta.access_token', '');
        $this->phoneNumberId = config('services.meta.phone_number_id', '');
        $this->apiVersion = config('services.meta.api_version', 'v21.0');
    }

    public function sendText(string $to, string $message): void
    {
        $this->send($to, [
            'type' => 'text',
            'text' => ['body' => $message, 'preview_url' => false],
        ]);
    }

    public function sendInteractiveButtons(string $to, string $body, array $buttons): void
    {
        $buttonList = array_map(fn ($btn, $i) => [
            'type' => 'reply',
            'reply' => ['id' => "btn_{$i}", 'title' => $btn],
        ], $buttons, array_keys($buttons));

        $this->send($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body],
                'action' => ['buttons' => $buttonList],
            ],
        ]);
    }

    public function sendList(string $to, string $header, string $body, string $buttonLabel, array $sections): void
    {
        $this->send($to, [
            'type' => 'interactive',
            'interactive' => [
                'type' => 'list',
                'header' => ['type' => 'text', 'text' => $header],
                'body' => ['text' => $body],
                'action' => [
                    'button' => $buttonLabel,
                    'sections' => $sections,
                ],
            ],
        ]);
    }

    private function send(string $to, array $messagePayload): void
    {
        if (empty($this->accessToken) || empty($this->phoneNumberId)) {
            Log::warning('WhatsApp messaging: credentials not configured', ['to' => $to]);
            return;
        }

        $payload = array_merge(['messaging_product' => 'whatsapp', 'to' => $to], $messagePayload);

        try {
            $response = Http::withToken($this->accessToken)
                ->post("https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages", $payload);

            if (!$response->successful()) {
                Log::error('WhatsApp send failed', ['error' => $response->json(), 'to' => $to]);
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp send exception', ['error' => $e->getMessage(), 'to' => $to]);
        }
    }
}

<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Services\WhatsApp\ConversationBotService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(private ConversationBotService $bot) {}

    // Meta verification: GET /webhook/whatsapp
    public function verify(Request $request): Response
    {
        $mode = $request->get('hub_mode');
        $token = $request->get('hub_verify_token');
        $challenge = $request->get('hub_challenge');

        if ($mode === 'subscribe' && $token === config('services.meta.verify_token')) {
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    // Incoming messages: POST /webhook/whatsapp
    public function handle(Request $request): Response
    {
        // Verify Meta signature
        if (!$this->verifySignature($request)) {
            Log::warning('WhatsApp webhook: invalid signature');
            return response('Unauthorized', 401);
        }

        $payload = $request->json()->all();
        Log::debug('WhatsApp webhook received', ['payload' => $payload]);

        try {
            foreach (data_get($payload, 'entry', []) as $entry) {
                foreach (data_get($entry, 'changes', []) as $change) {
                    $value = $change['value'] ?? [];

                    // Process messages
                    foreach ($value['messages'] ?? [] as $message) {
                        $phone = $message['from'];
                        $type = $message['type'];

                        if ($type === 'order') {
                            // Native WhatsApp catalog order
                            $this->bot->handleOrderMessage($phone, $message['order']);
                        } elseif ($type === 'text') {
                            $text = $message['text']['body'] ?? '';
                            $this->bot->handleTextMessage($phone, $text);
                        } elseif ($type === 'interactive') {
                            // Button or list reply
                            $reply = $message['interactive']['button_reply']['id']
                                ?? $message['interactive']['list_reply']['id']
                                ?? $message['interactive']['button_reply']['title']
                                ?? '';
                            $this->bot->handleTextMessage($phone, $reply);
                        }
                    }
                }
            }
        } catch (\Throwable $e) {
            Log::error('WhatsApp webhook processing error', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
        }

        return response('OK', 200);
    }

    private function verifySignature(Request $request): bool
    {
        $appSecret = config('services.meta.app_secret', '');

        if (empty($appSecret)) {
            // Skip verification if no secret configured (dev only)
            return true;
        }

        $signature = $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $appSecret);

        return hash_equals($expected, $signature);
    }
}

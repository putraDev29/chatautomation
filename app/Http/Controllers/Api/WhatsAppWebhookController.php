<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessIncomingWhatsAppMessage;
use App\Models\Message;
use App\Services\WhatsApp\WhatsAppMessageService;
use App\Services\WhatsApp\WhatsAppWebhookSignatureValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function __construct(
        protected WhatsAppWebhookSignatureValidator $signatureValidator,
        protected WhatsAppMessageService $messageService
    ) {}

    /**
     * Webhook verification challenge endpoint (Meta verification GET).
     */
    public function verify(Request $request): Response|JsonResponse
    {
        $mode = $request->query('hub_mode', $request->query('hub.mode'));
        $token = $request->query('hub_verify_token', $request->query('hub.verify_token'));
        $challenge = $request->query('hub_challenge', $request->query('hub.challenge'));

        $expectedToken = config('whatsapp.verify_token');

        if ($mode === 'subscribe' && $token === $expectedToken) {
            Log::info('[WHATSAPP WEBHOOK] Challenge verification successful.');
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        Log::warning('[WHATSAPP WEBHOOK] Challenge verification failed. Invalid verify token.');
        return response()->json(['error' => 'Forbidden: Invalid verify token.'], 403);
    }

    /**
     * Webhook incoming event receiver (Meta POST).
     */
    public function handle(Request $request): JsonResponse
    {
        // 1. Signature validation
        if (!$this->signatureValidator->isValid($request)) {
            Log::warning('[WHATSAPP WEBHOOK] Invalid HMAC SHA256 signature rejected.');
            return response()->json(['error' => 'Invalid signature.'], 401);
        }

        $payload = $request->all();

        // 2. Process status updates (sent, delivered, read, failed)
        if (!empty($payload['entry'][0]['changes'][0]['value']['statuses'])) {
            $this->handleStatusUpdates($payload['entry'][0]['changes'][0]['value']['statuses']);
            return response()->json(['status' => 'status_updated']);
        }

        // 3. Process incoming messages
        $messages = $payload['entry'][0]['changes'][0]['value']['messages'] ?? [];
        $contacts = $payload['entry'][0]['changes'][0]['value']['contacts'] ?? [];

        if (empty($messages)) {
            return response()->json(['status' => 'no_messages']);
        }

        $contactName = $contacts[0]['profile']['name'] ?? 'Customer';

        foreach ($messages as $msg) {
            $from = $msg['from'] ?? '';
            $msgId = $msg['id'] ?? ('META-WID-' . uniqid());
            $type = $msg['type'] ?? 'text';

            $body = match ($type) {
                'text' => $msg['text']['body'] ?? '',
                'image' => $msg['image']['caption'] ?? '[Foto Dikirim]',
                'video' => $msg['video']['caption'] ?? '[Video Dikirim]',
                'document' => $msg['document']['caption'] ?? '[Dokumen Dikirim]',
                'interactive' => $msg['interactive']['button_reply']['title'] ?? ($msg['interactive']['list_reply']['title'] ?? '[Pilihan Interaktif]'),
                default => '[Media/Pesan]',
            };

            // Dispatch job or process synchronously if queue is sync
            ProcessIncomingWhatsAppMessage::dispatch(
                fromNumber: $from,
                customerName: $contactName,
                messageBody: $body,
                waMessageId: $msgId,
                messageType: $type,
                metadata: [
                    'meta_timestamp' => $msg['timestamp'] ?? null,
                    'raw_type' => $type,
                ]
            );
        }

        return response()->json(['status' => 'received']);
    }

    /**
     * Update delivery statuses from Meta webhook.
     */
    protected function handleStatusUpdates(array $statuses): void
    {
        foreach ($statuses as $status) {
            $waMessageId = $status['id'] ?? null;
            $newStatus = $status['status'] ?? null;

            if ($waMessageId && $newStatus) {
                $msg = Message::where('wa_message_id', $waMessageId)->first();
                if ($msg) {
                    $validStatuses = ['sent', 'delivered', 'read', 'failed'];
                    if (in_array($newStatus, $validStatuses)) {
                        $msg->update([
                            'delivery_status' => $newStatus,
                            'error_code' => $status['errors'][0]['message'] ?? null,
                        ]);
                    }
                }
            }
        }
    }
}

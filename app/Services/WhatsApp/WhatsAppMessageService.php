<?php

namespace App\Services\WhatsApp;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\WhatsAppIntegration;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WhatsAppMessageService
{
    protected WhatsAppGatewayInterface $gateway;

    public function __construct(?WhatsAppGatewayInterface $gateway = null)
    {
        $this->gateway = $gateway ?: $this->resolveActiveGateway();
    }

    /**
     * Resolve appropriate gateway based on configuration and database integration settings.
     */
    public function resolveActiveGateway(): WhatsAppGatewayInterface
    {
        $integration = WhatsAppIntegration::current();

        if ($integration->whatsapp_mode === 'production' && config('whatsapp.mode') === 'production') {
            return app(MetaWhatsAppCloudGateway::class);
        }

        return app(SimulatedWhatsAppGateway::class);
    }

    public function getGateway(): WhatsAppGatewayInterface
    {
        return $this->gateway;
    }

    /**
     * Ingest an incoming message from webhook or simulation with strict idempotency.
     *
     * @param string $fromNumber International phone number (e.g. 6281234567890)
     * @param string $customerName Customer profile name if available
     * @param string $messageBody Content of message
     * @param string $waMessageId WhatsApp message ID (e.g. wamid.HBg...)
     * @param string $messageType text, image, video, document
     * @param array $metadata Sanitized metadata
     * @return array [is_duplicate => bool, conversation => Conversation, message => Message]
     */
    public function ingestIncomingMessage(
        string $fromNumber,
        string $customerName,
        string $messageBody,
        string $waMessageId,
        string $messageType = 'text',
        array $metadata = []
    ): array {
        $normalizedNumber = Customer::formatWhatsAppNumber($fromNumber);

        // 1. Idempotency Check
        $existingMessage = Message::where('wa_message_id', $waMessageId)->first();
        if ($existingMessage) {
            Log::info("[IDEMPOTENCY] WhatsApp message {$waMessageId} already processed. Skipping duplicate.");
            return [
                'is_duplicate' => true,
                'conversation' => $existingMessage->conversation,
                'message' => $existingMessage,
            ];
        }

        return DB::transaction(function () use ($normalizedNumber, $customerName, $messageBody, $waMessageId, $messageType, $metadata) {
            // 2. Find or create Customer
            $customer = Customer::firstOrCreate(
                ['whatsapp_number' => $normalizedNumber],
                [
                    'name' => !empty($customerName) ? $customerName : "Customer {$normalizedNumber}",
                    'customer_source' => 'WHATSAPP',
                    'communication_status' => 'ACTIVE',
                ]
            );

            $customer->update(['last_interaction_at' => Carbon::now()]);

            // 3. Find active conversation or create new
            $conversation = Conversation::where('customer_id', $customer->id)
                ->whereIn('status', ['BOT_ACTIVE', 'NEEDS_ADMIN', 'ADMIN_ACTIVE', 'WAITING_CUSTOMER'])
                ->latest()
                ->first();

            if (!$conversation) {
                $conversation = Conversation::create([
                    'customer_id' => $customer->id,
                    'status' => 'BOT_ACTIVE',
                    'is_bot_active' => true,
                    'is_admin_takeover' => false,
                    'assigned_admin_id' => $metadata['assigned_admin_id'] ?? null,
                    'wa_session_id' => $metadata['session_id'] ?? null,
                    'interest_level' => 'MEDIUM',
                    'last_message_at' => Carbon::now(),
                ]);
            } else {
                $updates = ['last_message_at' => Carbon::now()];
                if (!empty($metadata['assigned_admin_id']) && empty($conversation->assigned_admin_id)) {
                    $updates['assigned_admin_id'] = $metadata['assigned_admin_id'];
                }
                if (!empty($metadata['session_id']) && empty($conversation->wa_session_id)) {
                    $updates['wa_session_id'] = $metadata['session_id'];
                }
                $conversation->update($updates);
            }

            // 4. Record inbound message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'wa_message_id' => $waMessageId,
                'direction' => 'inbound',
                'sender_type' => 'customer',
                'message_type' => $messageType,
                'message_body' => $messageBody,
                'delivery_status' => 'delivered',
                'metadata' => $metadata,
                'sent_at' => Carbon::now(),
            ]);

            return [
                'is_duplicate' => false,
                'conversation' => $conversation->fresh(),
                'message' => $message,
            ];
        });
    }

    /**
     * Send an outbound message (from bot or admin) via active gateway and record in DB.
     */
    public function sendOutboundMessage(
        Conversation $conversation,
        string $text,
        string $senderType = 'bot',
        ?string $mediaUrl = null,
        ?string $mediaType = null,
        array $metadata = []
    ): Message {
        $customer = $conversation->customer;
        $toNumber = $customer->whatsapp_number;

        $gateway = $this->resolveActiveGateway();

        if (!empty($mediaUrl)) {
            $type = $mediaType ?: 'image';
            $result = $gateway->sendMediaMessage($toNumber, $type, $mediaUrl, $text, $metadata);
        } else {
            $result = $gateway->sendTextMessage($toNumber, $text, $metadata);
        }

        $message = Message::create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => $result['message_id'] ?? null,
            'direction' => 'outbound',
            'sender_type' => $senderType,
            'message_type' => !empty($mediaUrl) ? ($mediaType ?: 'image') : 'text',
            'message_body' => $text,
            'media_url' => $mediaUrl,
            'delivery_status' => $result['success'] ? 'sent' : 'failed',
            'error_code' => $result['error'] ?? null,
            'metadata' => array_merge($metadata, ['gateway_result' => $result['raw'] ?? []]),
            'sent_at' => Carbon::now(),
        ]);

        $conversation->update(['last_message_at' => Carbon::now()]);

        return $message;
    }
}

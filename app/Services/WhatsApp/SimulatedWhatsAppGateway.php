<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SimulatedWhatsAppGateway implements WhatsAppGatewayInterface
{
    public function getMode(): string
    {
        return 'simulation';
    }

    public function sendTextMessage(string $to, string $text, array $metadata = []): array
    {
        $messageId = 'SIM-TXT-' . Str::upper(Str::random(12));

        // Safe logging with masked phone number
        $maskedTo = substr($to, 0, 4) . '****' . substr($to, -3);
        Log::info("[SIMULATED WHATSAPP GATEWAY] Outbound text sent to {$maskedTo}: " . Str::limit($text, 80), [
            'simulated_id' => $messageId,
            'metadata' => $metadata,
        ]);

        return [
            'success' => true,
            'message_id' => $messageId,
            'error' => null,
            'raw' => [
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => $to, 'wa_id' => $to]],
                'messages' => [['id' => $messageId]],
                'simulation' => true,
            ],
        ];
    }

    public function sendMediaMessage(string $to, string $mediaType, string $mediaUrl, ?string $caption = null, array $metadata = []): array
    {
        $messageId = 'SIM-MED-' . Str::upper(Str::random(12));

        $maskedTo = substr($to, 0, 4) . '****' . substr($to, -3);
        Log::info("[SIMULATED WHATSAPP GATEWAY] Outbound {$mediaType} sent to {$maskedTo}: {$mediaUrl}", [
            'simulated_id' => $messageId,
            'caption' => $caption,
            'metadata' => $metadata,
        ]);

        return [
            'success' => true,
            'message_id' => $messageId,
            'error' => null,
            'raw' => [
                'messaging_product' => 'whatsapp',
                'contacts' => [['input' => $to, 'wa_id' => $to]],
                'messages' => [['id' => $messageId]],
                'media_type' => $mediaType,
                'media_url' => $mediaUrl,
                'caption' => $caption,
                'simulation' => true,
            ],
        ];
    }
}

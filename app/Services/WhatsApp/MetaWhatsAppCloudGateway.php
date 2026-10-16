<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class MetaWhatsAppCloudGateway implements WhatsAppGatewayInterface
{
    protected string $apiVersion;
    protected string $phoneNumberId;
    protected string $accessToken;
    protected string $mode;
    protected int $timeout;
    protected int $maxRetries;

    public function __construct()
    {
        $this->mode = config('whatsapp.mode', 'simulation');
        $this->apiVersion = config('whatsapp.api_version', 'v21.0');
        $this->phoneNumberId = (string) config('whatsapp.phone_number_id', '');
        $this->accessToken = (string) config('whatsapp.access_token', '');
        $this->timeout = (int) config('whatsapp.timeout_seconds', 15);
        $this->maxRetries = (int) config('whatsapp.max_retries', 3);
    }

    public function getMode(): string
    {
        return 'production';
    }

    /**
     * Send text message through Meta WhatsApp Cloud API.
     */
    public function sendTextMessage(string $to, string $text, array $metadata = []): array
    {
        $this->assertProductionModeAllowed();

        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => [
                'preview_url' => false,
                'body' => $text,
            ],
        ];

        return $this->executeRequest($url, $payload, $to);
    }

    /**
     * Send media message (image / video) through Meta WhatsApp Cloud API.
     */
    public function sendMediaMessage(string $to, string $mediaType, string $mediaUrl, ?string $caption = null, array $metadata = []): array
    {
        $this->assertProductionModeAllowed();

        $type = in_array($mediaType, ['video', 'image']) ? $mediaType : 'image';
        $url = "https://graph.facebook.com/{$this->apiVersion}/{$this->phoneNumberId}/messages";

        $mediaPayload = ['link' => $mediaUrl];
        if (!empty($caption)) {
            $mediaPayload['caption'] = $caption;
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => $type,
            $type => $mediaPayload,
        ];

        return $this->executeRequest($url, $payload, $to);
    }

    /**
     * Execute HTTP call to Meta Graph API with timeout and retries.
     */
    protected function executeRequest(string $url, array $payload, string $to): array
    {
        $maskedTo = substr($to, 0, 4) . '****' . substr($to, -3);

        try {
            $response = Http::withToken($this->accessToken)
                ->timeout($this->timeout)
                ->retry($this->maxRetries, 500, throw: false)
                ->post($url, $payload);

            if ($response->successful()) {
                $data = $response->json();
                $messageId = $data['messages'][0]['id'] ?? 'META-UNKNOWN-ID';

                Log::info("[META CLOUD API] Message successfully sent to {$maskedTo}", [
                    'message_id' => $messageId,
                ]);

                return [
                    'success' => true,
                    'message_id' => $messageId,
                    'error' => null,
                    'raw' => $data,
                ];
            }

            $errorBody = $response->json() ?? [];
            $errorMessage = $errorBody['error']['message'] ?? 'Meta API error HTTP ' . $response->status();
            $errorCode = $errorBody['error']['code'] ?? $response->status();

            Log::error("[META CLOUD API ERROR] Failed to send to {$maskedTo}", [
                'code' => $errorCode,
                'message' => $errorMessage,
            ]);

            return [
                'success' => false,
                'message_id' => null,
                'error' => $errorMessage,
                'raw' => $errorBody,
            ];
        } catch (\Throwable $e) {
            Log::error("[META CLOUD API EXCEPTION] Exception when sending to {$maskedTo}: " . $e->getMessage());

            return [
                'success' => false,
                'message_id' => null,
                'error' => $e->getMessage(),
                'raw' => [],
            ];
        }
    }

    /**
     * Safety check preventing any live Meta dispatch unless explicitly configured for production.
     */
    public function assertProductionModeAllowed(): void
    {
        if ($this->mode !== 'production') {
            throw new RuntimeException("CRITICAL SAFETY BLOCK: Production WhatsApp dispatch is disallowed when WHATSAPP_MODE is set to '{$this->mode}'. Use SimulatedWhatsAppGateway for local development.");
        }

        if (empty($this->phoneNumberId) || empty($this->accessToken)) {
            throw new RuntimeException("CRITICAL CONFIGURATION ERROR: WHATSAPP_PHONE_NUMBER_ID and WHATSAPP_ACCESS_TOKEN must be configured before enabling production mode.");
        }
    }
}

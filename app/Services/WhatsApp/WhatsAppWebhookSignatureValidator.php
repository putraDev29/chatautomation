<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Request;

class WhatsAppWebhookSignatureValidator
{
    /**
     * Verify whether incoming request has a valid Meta Hub Signature.
     */
    public function isValid(Request $request): bool
    {
        $appSecret = config('whatsapp.app_secret');

        // In simulation mode without secret configured, allow local testing
        if (empty($appSecret) && config('whatsapp.mode') === 'simulation') {
            return true;
        }

        $header = $request->header('X-Hub-Signature-256');
        if (empty($header) || !str_starts_with($header, 'sha256=')) {
            return false;
        }

        $signature = substr($header, 7);
        $expectedSignature = hash_hmac('sha256', $request->getContent(), (string) $appSecret);

        return hash_equals($expectedSignature, $signature);
    }
}

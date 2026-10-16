<?php

namespace App\Services\WhatsApp;

interface WhatsAppGatewayInterface
{
    /**
     * Get gateway mode: 'simulation' or 'production'
     */
    public function getMode(): string;

    /**
     * Send text message to recipient.
     *
     * @param string $to International phone number without plus sign (e.g. 6281234567890)
     * @param string $text Message content
     * @param array $metadata Optional metadata
     * @return array [success => bool, message_id => string, error => ?string, raw => array]
     */
    public function sendTextMessage(string $to, string $text, array $metadata = []): array;

    /**
     * Send media message (image or video) to recipient.
     *
     * @param string $to International phone number
     * @param string $mediaType 'image' | 'video'
     * @param string $mediaUrl Public HTTPS URL or local relative path (in simulation)
     * @param string|null $caption Optional caption
     * @param array $metadata Optional metadata
     * @return array [success => bool, message_id => string, error => ?string, raw => array]
     */
    public function sendMediaMessage(string $to, string $mediaType, string $mediaUrl, ?string $caption = null, array $metadata = []): array;
}

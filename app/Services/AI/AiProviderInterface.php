<?php

namespace App\Services\AI;

interface AiProviderInterface
{
    /**
     * Get the driver name: 'simulation', 'openai', etc.
     */
    public function getDriverName(): string;

    /**
     * Process customer input message with context and return structured assistant decision.
     *
     * @param string $message Customer message text
     * @param array $context Context containing customer, conversation history, and current interested car
     * @return array [
     *    'reply_text' => string,
     *    'media_url' => ?string,
     *    'media_type' => ?string,
     *    'tool_called' => ?string,
     *    'handover_required' => bool,
     *    'handover_reason' => ?string,
     *    'interested_car_id' => ?int,
     * ]
     */
    public function process(string $message, array $context = []): array;
}

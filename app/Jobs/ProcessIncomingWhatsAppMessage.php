<?php

namespace App\Jobs;

use App\Services\AI\AiSalesAssistantService;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessIncomingWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $fromNumber,
        public string $customerName,
        public string $messageBody,
        public string $waMessageId,
        public string $messageType = 'text',
        public array $metadata = []
    ) {}

    public function handle(WhatsAppMessageService $messageService, AiSalesAssistantService $aiAssistant): void
    {
        Log::info("[QUEUE JOB] Processing incoming WhatsApp message {$this->waMessageId} from {$this->fromNumber}");

        $result = $messageService->ingestIncomingMessage(
            $this->fromNumber,
            $this->customerName,
            $this->messageBody,
            $this->waMessageId,
            $this->messageType,
            $this->metadata
        );

        if ($result['is_duplicate']) {
            Log::info("[QUEUE JOB] Skipping duplicate message {$this->waMessageId}");
            return;
        }

        $conversation = $result['conversation'];
        $message = $result['message'];

        // Automatically trigger AI Sales Assistant
        $aiAssistant->handleIncomingMessage($conversation, $message);
    }
}

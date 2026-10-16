<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $conversationId,
        public string $text,
        public string $senderType = 'admin',
        public ?string $mediaUrl = null,
        public ?string $mediaType = null,
        public array $metadata = []
    ) {}

    public function handle(WhatsAppMessageService $messageService): void
    {
        $conversation = Conversation::find($this->conversationId);
        if (!$conversation) {
            Log::error("[QUEUE JOB] Conversation #{$this->conversationId} not found when dispatching outbound WhatsApp message.");
            return;
        }

        $messageService->sendOutboundMessage(
            $conversation,
            $this->text,
            $this->senderType,
            $this->mediaUrl,
            $this->mediaType,
            $this->metadata
        );
    }
}

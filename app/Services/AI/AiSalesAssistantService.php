<?php

namespace App\Services\AI;

use App\Models\Conversation;
use App\Models\Message;
use App\Models\WhatsAppIntegration;
use App\Services\Cars\CarMediaService;
use App\Services\Cars\CarSearchService;
use App\Services\Conversations\ConversationHandoverService;
use App\Services\Leads\LeadManagementService;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Support\Facades\Log;

class AiSalesAssistantService
{
    public function __construct(
        protected WhatsAppMessageService $messageService,
        protected ConversationHandoverService $handoverService,
        protected LeadManagementService $leadService,
        protected SimulatedAiProvider $simulatedProvider,
        protected OpenAiProvider $openAiProvider,
        protected ?CarSearchService $carSearchService = null,
        protected ?CarMediaService $carMediaService = null
    ) {
        $this->carSearchService = $carSearchService ?? app(CarSearchService::class);
        $this->carMediaService = $carMediaService ?? app(CarMediaService::class);
    }

    /**
     * Resolve the configured AI provider.
     */
    public function resolveProvider(): AiProviderInterface
    {
        $integration = WhatsAppIntegration::current();
        $driver = $integration->ai_driver ?: config('ai.driver', 'simulation');

        if ($driver === 'openai' && !empty(config('ai.api_key'))) {
            return $this->openAiProvider;
        }

        return $this->simulatedProvider;
    }

    /**
     * Process an incoming customer message and auto-reply if bot is active.
     *
     * @param Conversation $conversation
     * @param Message $incomingMessage
     * @return Message|null Returns created outbound message, or null if bot is paused / takeover active
     */
    public function handleIncomingMessage(Conversation $conversation, Message $incomingMessage): ?Message
    {
        $integration = WhatsAppIntegration::current();

        // 1. Guard: Check if AI is enabled globally
        if (!$integration->is_ai_enabled) {
            Log::info("[AI SERVICE] AI is currently disabled globally. No automatic reply generated.");
            return null;
        }

        // 2. Guard: Check if bot is paused or admin has taken over
        $isControlledByAdmin = (!$conversation->is_bot_active || $conversation->is_admin_takeover || $conversation->status === 'ADMIN_ACTIVE');

        if ($isControlledByAdmin) {
            // Check if customer explicitly demands to talk to a human admin / sales operator
            $demandsAdmin = $this->isExplicitAdminDemanded($incomingMessage->message_body ?? '');

            if (!$demandsAdmin) {
                Log::info("[AI SERVICE] Customer mengirim pesan baru di percakapan #{$conversation->id} yang tidak memerlukan admin langsung. Bot otomatis aktif kembali tanpa harus menekan tombol aktifkan bot di dashboard.");
                $this->handoverService->reactivateBot($conversation);
                $conversation->refresh();
            } else {
                Log::info("[AI SERVICE] Percakapan #{$conversation->id} sedang ditangani admin dan customer meminta bicara langsung dengan admin. Bot tetap hening.");
                return null;
            }
        }

        $customer = $conversation->customer;

        // Synchronize car context from conversation or recent message history
        $interestedCarId = $conversation->interested_car_id;
        if (!$interestedCarId) {
            $interestedCarId = $this->detectCarFromConversationHistory($conversation);
            if ($interestedCarId) {
                $conversation->update(['interested_car_id' => $interestedCarId]);
            }
        }

        // Retrieve recent messages for full multi-turn conversational synchronization
        $recentMessages = $conversation->messages()
            ->where('id', '!=', $incomingMessage->id)
            ->orderBy('created_at', 'desc')
            ->limit(8)
            ->get()
            ->reverse()
            ->map(fn($m) => [
                'role' => $m->direction === 'inbound' ? 'user' : 'assistant',
                'body' => $m->message_body ?? '',
            ])
            ->values()
            ->toArray();

        $context = [
            'customer_name' => $customer->name,
            'whatsapp_number' => $customer->whatsapp_number,
            'interested_car_id' => $interestedCarId,
            'conversation_status' => $conversation->status,
            'recent_messages' => $recentMessages,
        ];

        // 3. Process with AI Provider
        $provider = $this->resolveProvider();
        $decision = $provider->process($incomingMessage->message_body ?? '', $context);

        // 4. Update Interested Car if detected or switched
        if (array_key_exists('interested_car_id', $decision) && $conversation->interested_car_id !== $decision['interested_car_id']) {
            $conversation->update(['interested_car_id' => $decision['interested_car_id']]);
        }

        // 5. Handle Admin Handover if required
        if (!empty($decision['handover_required'])) {
            $this->handoverService->requestHandover(
                $conversation,
                $decision['handover_reason'] ?? 'Customer membutuhkan tindak lanjut admin sales.',
                'AUTO_INTENT',
                ['triggered_by_message_id' => $incomingMessage->id]
            );

            // Sync lead
            $this->leadService->syncLeadFromConversation($conversation, [
                'lead_status' => 'HOT',
                'interest_level' => 'HOT',
                'notes' => $decision['handover_reason'],
            ]);
        }

        // 6. Record usage in integrations
        $integration->increment('current_daily_ai_usage');

        // 7. Send bot reply through WhatsApp Gateway
        $replyText = $decision['reply_text'] ?? 'Terima kasih atas pesan Anda!';
        $mediaUrl = $decision['media_url'] ?? null;
        $mediaType = $decision['media_type'] ?? null;

        return $this->messageService->sendOutboundMessage(
            $conversation,
            $replyText,
            'bot',
            $mediaUrl,
            $mediaType,
            [
                'ai_provider' => $provider->getDriverName(),
                'tool_called' => $decision['tool_called'] ?? null,
            ]
        );
    }

    /**
     * Check if a message explicitly demands a human admin/salesperson or is addressing admin directly.
     */
    public function isExplicitAdminDemanded(string $message): bool
    {
        $textLower = strtolower(trim($message));
        if (empty($textLower)) {
            return false;
        }

        $adminDemandKeywords = [
            'admin', 'mas admin', 'pak admin', 'halo admin', 'min', 'halo min',
            'bicara admin', 'ngomong sama orang', 'hubungkan sales', 'sales showroom',
            'bukan bot', 'cs manusia', 'bicara sama sales', 'minta nomor sales',
            'owner showroom', 'operator manusia', 'mau bicara dengan admin',
            'hubungi admin', 'sambungkan ke admin'
        ];

        foreach ($adminDemandKeywords as $kw) {
            if (str_contains($textLower, $kw)) {
                return true;
            }
        }

        $adminAcks = [
            'oke ditunggu', 'baik saya tunggu', 'terima kasih', 'makasih', 'siap'
        ];
        foreach ($adminAcks as $ack) {
            if (str_contains($textLower, $ack) && !str_contains($textLower, '?') && !str_contains($textLower, 'tanya') && !str_contains($textLower, 'mobil') && !str_contains($textLower, 'harga')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Determine if a customer message is an inquiry about cars, stock, trade-in, or showroom services.
     */
    public function isCarOrShowroomInquiry(string $message): bool
    {
        $textLower = strtolower(trim($message));
        if (empty($textLower)) {
            return false;
        }

        // 1. Explicit admin greetings/acknowledgements without car questions
        $adminPureGreetings = [
            'halo admin', 'pagi admin', 'siang admin', 'sore admin', 'malam admin',
            'admin sudah siap', 'halo min', 'terima kasih admin', 'makasih admin',
            'tunggu admin', 'sudah siap'
        ];
        foreach ($adminPureGreetings as $apg) {
            if (str_contains($textLower, $apg) && !str_contains($textLower, 'mobil') && !str_contains($textLower, 'tukar') && !str_contains($textLower, 'ready') && !str_contains($textLower, 'harga')) {
                return false;
            }
        }

        // 2. Structured car search attributes (model, brand, body_type, budget, year)
        $parsed = $this->carSearchService->parseQueryFromText($textLower);
        if (!empty($parsed['model']) || !empty($parsed['brand']) || !empty($parsed['body_type']) || !empty($parsed['max_price']) || !empty($parsed['transmission'])) {
            return true;
        }

        // 3. Trade-in / Tukar Tambah keywords & abbreviations (TT, BT, Trade In)
        if (preg_match('/\b(tt|t\.t|bt|trade\s*in|tukar\s*tambah|tuker\s*tambah|tukar\s*mobil|tuker\s*mobil|tukar\s*unit|tuker\s*unit|tukar|tuker)\b/i', $textLower)) {
            return true;
        }

        // Customer mentions their own car specs & condition (mobil saya, punya saya, ada mobil, dll.)
        if (preg_match('/\b(mobil saya|unit saya|ada mobil|punya saya|mobilku|punyaku|spek mobil|kondisi mobil)\b/i', $textLower)) {
            return true;
        }

        // Automotive abbreviations (DP, TDP, SS, MT, AT, KM, BBN, OTR, TD, PJK, No PR)
        if (preg_match('/\b(dp|tdp|ss|mt|at|km|bbn|otr|td|odo|pjk|no pr)\b/i', $textLower)) {
            return true;
        }

        // 4. Stock & availability keywords
        $stockKeywords = [
            'ready', 'stok', 'tersedia', 'standby', 'ada mobil', 'unit apa', 'mobil apa', 
            'stok showroom', 'pilihan mobil', 'list mobil', 'daftar mobil', 'semua mobil', 
            'semua unit', 'unit ready', 'ada unit', 'semua list'
        ];
        foreach ($stockKeywords as $kw) {
            if (str_contains($textLower, $kw)) {
                return true;
            }
        }

        // 5. Media requests (photos, videos)
        if ($this->carMediaService->detectRequestedMediaType($textLower) !== null) {
            return true;
        }

        // 6. Buying, selling, price, specs, documents, warranty FAQ keywords
        $faqKeywords = [
            'harga', 'berapa', 'biaya', 'cash', 'kredit', 'cicilan', 'angsuran', 'dp', 'leasing',
            'jual mobil', 'beli mobil', 'titip jual', 'taksir',
            'warna', 'warnanya', 'garansi', 'kondisi', 'inspeksi', 'banjir', 'tabrak', 'kilometer', 'odometer', 'surat', 'stnk', 'bpkb', 'faktur', 'pajak', 'balik nama',
            'booking', 'tanda jadi', 'kirim', 'ongkir', 'towing', 'lokasi', 'showroom', 'mulus', 'mls', 'ori', 'orisinil', 'kaleng', 'siap pakai'
        ];
        foreach ($faqKeywords as $kw) {
            if (str_contains($textLower, $kw)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inspect recent conversation messages to detect any car model previously mentioned.
     */
    public function detectCarFromConversationHistory(Conversation $conversation): ?int
    {
        $messages = $conversation->messages()
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        foreach ($messages as $msg) {
            $text = $msg->message_body ?? '';
            if (empty($text)) {
                continue;
            }
            $parsed = $this->carSearchService->parseQueryFromText($text);
            if (!empty($parsed['model']) || !empty($parsed['brand'])) {
                $found = $this->carSearchService->search($parsed)->first();
                if ($found) {
                    return $found->id;
                }
            }
        }

        return null;
    }
}

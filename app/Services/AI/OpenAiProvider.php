<?php

namespace App\Services\AI;

use App\Models\Car;
use App\Services\Cars\CarMediaService;
use App\Services\Cars\CarSearchService;
use App\Services\Conversations\ConversationHandoverService;
use App\Services\Shipping\ShippingInformationService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiProvider implements AiProviderInterface
{
    public function __construct(
        protected CarSearchService $carSearchService,
        protected CarMediaService $carMediaService,
        protected ShippingInformationService $shippingService,
        protected ConversationHandoverService $handoverService,
        protected SimulatedAiProvider $simulatedFallback
    ) {}

    public function getDriverName(): string
    {
        return 'openai';
    }

    public function process(string $message, array $context = []): array
    {
        $apiKey = config('ai.api_key');
        if (empty($apiKey)) {
            Log::warning("[OPENAI PROVIDER] AI_API_KEY is empty. Falling back to SimulatedAiProvider.");
            return $this->simulatedFallback->process($message, $context);
        }

        try {
            // First check if high priority handover intent is triggered locally
            $handoverCheck = $this->handoverService->detectHandoverTrigger($message);
            if ($handoverCheck) {
                return $this->simulatedFallback->process($message, $context);
            }

            $model = config('ai.model', 'gpt-4o-mini');
            $timeout = (int) config('ai.timeout', 30);
            $customerName = $context['customer_name'] ?? 'Customer';

            $availableCars = $this->carSearchService->getAllAvailableCars();
            $inventoryText = $availableCars->map(fn($c) => "- {$c->full_name} | Harga: {$c->formatted_price} | Transmisi: {$c->transmission} | Warna: {$c->color} | KM: {$c->formatted_mileage} | Pajak: STNK {$c->stnk_status} (BPKB {$c->bpkb_status})")->implode("\n");

            $systemPrompt = "Anda adalah Customer Service Assistant Showroom Bintang Mobil Surabaya yang profesional, ramah, sopan, dan ringkas. "
                . "HANYA berikan informasi mobil berdasarkan data stok showroom berikut:\n"
                . $inventoryText . "\n\n"
                . "ATURAN WAJIB:\n"
                . "1. DILARANG KERAS mengarang stok, harga, kilometer, diskon, atau unit fiktif.\n"
                . "2. Jika customer bertanya 'mobil apa saja yang ready' atau minta tampilkan listnya, tampilkan SEMUA list mobil yang ready di atas secara lengkap tanpa memotong.\n"
                . "3. Jika customer menanyakan unit dengan tahun tertentu yang tidak ada (misal Innova Reborn 2022) tapi showroom punya unit model sama di tahun lain (misal 2021), JANGAN bilang stok kosong! Tetap tampilkan unit yang ada dan tambahkan kalimat: 'Untuk [Model] tahun [Tahun dicari] saat ini kebetulan belum ada Kak, yang ready saat ini ada [Model tahun tersedia]'.\n"
                . "4. Jangan kirim foto/video kecuali customer secara jelas memintanya.\n"
                . "5. Jika customer ingin negosiasi harga, minta test drive, atau booking, wajib arahkan untuk dihubungkan dengan Admin Bintang Mobil.\n"
                . "6. SINKRONISASI KONTEKS MOBIL & WARNA: Jika customer menanyakan warna (misal: 'warna apa saja yang ready?' atau 'warnanya apa?'), WAJIB sinkronkan dengan mobil yang sedang ditanyakan sebelumnya pada riwayat obrolan atau konteks aktif. Sebutkan warna unit mobil tersebut yang ready di stok showroom. Jika sebelumnya belum ada mobil yang ditanyakan, berikan pilihan warna mobil ready secara umum dan tanyakan tipe mobil apa yang dicari.\n"
                . "Gunakan bahasa Indonesia yang natural dan ramah.";

            $messages = [
                ['role' => 'system', 'content' => $systemPrompt],
            ];

            // If an interested car is established, inject as context
            if (!empty($context['interested_car_id'])) {
                $activeCar = Car::find($context['interested_car_id']);
                if ($activeCar) {
                    $messages[] = [
                        'role' => 'system',
                        'content' => "[Konteks: Customer sebelumnya sedang tertarik / membahas unit mobil: {$activeCar->full_name} (Warna: {$activeCar->color}, Harga: {$activeCar->formatted_price}, Transmisi: {$activeCar->transmission})]",
                    ];
                }
            }

            // Include previous conversational history
            if (!empty($context['recent_messages']) && is_array($context['recent_messages'])) {
                foreach ($context['recent_messages'] as $past) {
                    $messages[] = [
                        'role' => $past['role'] === 'user' ? 'user' : 'assistant',
                        'content' => $past['body'],
                    ];
                }
            }

            $messages[] = ['role' => 'user', 'content' => "Pesan dari {$customerName}: {$message}"];

            $response = Http::withToken($apiKey)
                ->timeout($timeout)
                ->post('https://api.openai.com/v1/chat/completions', [
                    'model' => $model,
                    'messages' => $messages,
                    'max_tokens' => (int) config('ai.max_tokens', 500),
                    'temperature' => 0.5,
                ]);

            if ($response->successful()) {
                $content = $response->json('choices.0.message.content') ?? '';
                return [
                    'reply_text' => trim($content),
                    'media_url' => null,
                    'media_type' => null,
                    'tool_called' => 'openai_completion',
                    'handover_required' => false,
                    'handover_reason' => null,
                    'interested_car_id' => $context['interested_car_id'] ?? null,
                ];
            }

            Log::error("[OPENAI API ERROR] " . $response->body());
            return $this->simulatedFallback->process($message, $context);
        } catch (\Throwable $e) {
            Log::error("[OPENAI EXCEPTION] " . $e->getMessage());
            return $this->simulatedFallback->process($message, $context);
        }
    }
}

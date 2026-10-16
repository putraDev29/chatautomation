<?php

namespace App\Services\Conversations;

use App\Models\Conversation;
use App\Models\HandoverLog;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConversationHandoverService
{
    /**
     * Check whether a customer message triggers an automatic handover to admin.
     */
    public function detectHandoverTrigger(string $message): ?array
    {
        $lower = strtolower($message);

        // Intent patterns
        $patterns = [
            'NEGOTIATION' => [
                'keywords' => ['nego', 'bisa kurang', 'harga pas', 'nett', 'diskon', 'potongan harga', 'tawar', 'harga terbaik'],
                'reason' => 'Customer menanyakan negosiasi harga atau diskon khusus.',
            ],
            'TEST_DRIVE' => [
                'keywords' => ['test drive', 'testdrive', 'tes jalan', 'coba jalan', 'jadwal ketemu', 'ketemu di showroom'],
                'reason' => 'Customer mengajukan jadwal test drive atau kunjungan showroom.',
            ],
            'BOOKING' => [
                'keywords' => ['booking', 'tanda jadi', 'dp', 'uang muka', 'keep mobil', 'kunci unit', 'transfer dp'],
                'reason' => 'Customer ingin melakukan booking / pembayaran tanda jadi unit.',
            ],
            'CREDIT_CALCULATION' => [
                'keywords' => ['kredit', 'cicilan', 'angsuran', 'leasing', 'dp berapa', 'tenor'],
                'reason' => 'Customer meminta perhitungan simulasi kredit / leasing terperinci.',
            ],
            'TALK_TO_HUMAN' => [
                'keywords' => ['bicara admin', 'ngomong sama orang', 'hubungkan sales', 'sales showroom', 'bukan bot', 'operator', 'cs manusia'],
                'reason' => 'Customer meminta berbicara langsung dengan sales/admin manusia.',
            ],
            'HIGH_PURCHASE_INTENT' => [
                'keywords' => ['saya serius', 'serius beli', 'mau beli sekarang', 'mau ambil unit', 'deal'],
                'reason' => 'Customer menunjukkan minat beli sangat tinggi (hot lead).',
            ],
        ];

        foreach ($patterns as $intent => $data) {
            foreach ($data['keywords'] as $kw) {
                if (str_contains($lower, $kw)) {
                    return [
                        'intent' => $intent,
                        'reason' => $data['reason'],
                    ];
                }
            }
        }

        return null;
    }

    /**
     * Trigger handover to admin.
     */
    public function requestHandover(Conversation $conversation, string $reason, string $triggerType = 'AUTO_INTENT', array $metadata = []): Conversation
    {
        return DB::transaction(function () use ($conversation, $reason, $triggerType, $metadata) {
            $conversation->update([
                'status' => 'NEEDS_ADMIN',
                'is_bot_active' => false,
                'handover_at' => Carbon::now(),
                'handover_reason' => $reason,
                'interest_level' => in_array($conversation->interest_level, ['HIGH', 'HOT']) ? 'HOT' : 'HIGH',
            ]);

            HandoverLog::create([
                'conversation_id' => $conversation->id,
                'trigger_type' => $triggerType,
                'action' => 'HANDOVER_REQUESTED',
                'reason' => $reason,
                'metadata' => $metadata,
            ]);

            return $conversation->fresh();
        });
    }

    /**
     * Admin takes over the conversation.
     */
    public function takeoverByAdmin(Conversation $conversation, User $admin, ?string $note = null): Conversation
    {
        return DB::transaction(function () use ($conversation, $admin, $note) {
            $conversation->update([
                'status' => 'ADMIN_ACTIVE',
                'is_bot_active' => false,
                'is_admin_takeover' => true,
                'assigned_admin_id' => $admin->id,
                'internal_notes' => $note ?: $conversation->internal_notes,
            ]);

            HandoverLog::create([
                'conversation_id' => $conversation->id,
                'trigger_type' => 'MANUAL_ADMIN',
                'action' => 'ADMIN_TAKEOVER',
                'reason' => "Admin {$admin->name} mengambil alih percakapan.",
                'assigned_to_user_id' => $admin->id,
                'performed_by_user_id' => $admin->id,
            ]);

            return $conversation->fresh();
        });
    }

    /**
     * Reactivate bot for this conversation.
     */
    public function reactivateBot(Conversation $conversation, ?User $admin = null): Conversation
    {
        return DB::transaction(function () use ($conversation, $admin) {
            $conversation->update([
                'status' => 'BOT_ACTIVE',
                'is_bot_active' => true,
                'is_admin_takeover' => false,
            ]);

            HandoverLog::create([
                'conversation_id' => $conversation->id,
                'trigger_type' => 'MANUAL_ADMIN',
                'action' => 'BOT_RESUMED',
                'reason' => 'Bot AI diaktifkan kembali untuk melayani pesan otomatis.',
                'performed_by_user_id' => $admin?->id,
            ]);

            return $conversation->fresh();
        });
    }
}

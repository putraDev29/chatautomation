<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Models\User;
use App\Services\AI\AiSalesAssistantService;
use App\Services\Conversations\ConversationHandoverService;
use Database\Seeders\BintangMobilDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HandoverAndAdminTakeoverTest extends TestCase
{
    use RefreshDatabase;

    protected ConversationHandoverService $handoverService;
    protected AiSalesAssistantService $aiAssistant;
    protected User $admin;
    protected Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BintangMobilDemoSeeder::class);

        $this->handoverService = app(ConversationHandoverService::class);
        $this->aiAssistant = app(AiSalesAssistantService::class);
        $this->admin = User::first();

        $customer = Customer::create([
            'name' => 'Bambang Soesatyo',
            'whatsapp_number' => '6281122334455',
        ]);

        $this->conversation = Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'BOT_ACTIVE',
            'is_bot_active' => true,
            'is_admin_takeover' => false,
        ]);
    }

    public function test_customer_negotiation_triggers_handover(): void
    {
        $incoming = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'Saya serius mau beli hari ini, ada diskon harga pas gak min?',
            'delivery_status' => 'delivered',
        ]);

        $reply = $this->aiAssistant->handleIncomingMessage($this->conversation, $incoming);

        $this->conversation->refresh();
        $this->assertEquals('NEEDS_ADMIN', $this->conversation->status);
        $this->assertFalse($this->conversation->is_bot_active);
        $this->assertDatabaseHas('wa_handover_logs', [
            'conversation_id' => $this->conversation->id,
            'action' => 'HANDOVER_REQUESTED',
        ]);
    }

    public function test_admin_takeover_stops_bot_from_replying(): void
    {
        // Admin takes over
        $this->handoverService->takeoverByAdmin($this->conversation, $this->admin);

        $this->conversation->refresh();
        $this->assertEquals('ADMIN_ACTIVE', $this->conversation->status);
        $this->assertTrue($this->conversation->is_admin_takeover);
        $this->assertFalse($this->conversation->is_bot_active);

        // Subsequent message arrives from customer
        $newCustomerMsg = Message::create([
            'conversation_id' => $this->conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'Halo admin, sudah siap?',
            'delivery_status' => 'delivered',
        ]);

        // AiAssistant MUST NOT generate any bot response
        $botReply = $this->aiAssistant->handleIncomingMessage($this->conversation, $newCustomerMsg);
        $this->assertNull($botReply);
    }

    public function test_admin_resumes_bot_successfully(): void
    {
        // Takeover first
        $this->handoverService->takeoverByAdmin($this->conversation, $this->admin);

        // Resume bot
        $this->handoverService->reactivateBot($this->conversation, $this->admin);

        $this->conversation->refresh();
        $this->assertEquals('BOT_ACTIVE', $this->conversation->status);
        $this->assertTrue($this->conversation->is_bot_active);
        $this->assertFalse($this->conversation->is_admin_takeover);

        $this->assertDatabaseHas('wa_handover_logs', [
            'conversation_id' => $this->conversation->id,
            'action' => 'BOT_RESUMED',
        ]);
    }
}

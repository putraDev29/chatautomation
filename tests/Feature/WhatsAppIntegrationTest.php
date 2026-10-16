<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\WhatsApp\MetaWhatsAppCloudGateway;
use App\Services\WhatsApp\SimulatedWhatsAppGateway;
use App\Services\WhatsApp\WhatsAppMessageService;
use Database\Seeders\BintangMobilDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class WhatsAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BintangMobilDemoSeeder::class);
    }

    public function test_webhook_challenge_verification_succeeds_with_valid_token(): void
    {
        $verifyToken = config('whatsapp.verify_token', 'bintang_mobil_verify_token_2026');

        $response = $this->get('/api/whatsapp/webhook?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => $verifyToken,
            'hub_challenge' => '1234567890',
        ]));

        $response->assertStatus(200);
        $this->assertEquals('1234567890', $response->getContent());
    }

    public function test_webhook_challenge_fails_with_invalid_token(): void
    {
        $response = $this->get('/api/whatsapp/webhook?' . http_build_query([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'wrong_token',
            'hub_challenge' => '1234567890',
        ]));

        $response->assertStatus(403);
    }

    public function test_incoming_webhook_message_is_ingested_idempotently(): void
    {
        $payload = [
            'object' => 'whatsapp_business_account',
            'entry' => [
                [
                    'id' => 'WHATSAPP_BUSINESS_ACCOUNT_ID',
                    'changes' => [
                        [
                            'value' => [
                                'messaging_product' => 'whatsapp',
                                'metadata' => [
                                    'display_phone_number' => '15550269999',
                                    'phone_number_id' => '123456789',
                                ],
                                'contacts' => [
                                    [
                                        'profile' => ['name' => 'Bambang Yudhoyono'],
                                        'wa_id' => '6281299988877',
                                    ],
                                ],
                                'messages' => [
                                    [
                                        'from' => '6281299988877',
                                        'id' => 'wamid.HBgLTESTMESSAGE001',
                                        'timestamp' => '1728550000',
                                        'type' => 'text',
                                        'text' => ['body' => 'Avanza 2022 ada gak min?'],
                                    ],
                                ],
                            ],
                            'field' => 'messages',
                        ],
                    ],
                ],
            ],
        ];

        // First Post (Must succeed and record message)
        $response1 = $this->postJson('/api/whatsapp/webhook', $payload);
        $response1->assertStatus(200);

        $this->assertDatabaseHas('wa_customers', ['whatsapp_number' => '6281299988877']);
        $this->assertDatabaseHas('wa_messages', ['wa_message_id' => 'wamid.HBgLTESTMESSAGE001']);
        $initialMessageCount = Message::count();

        // Duplicate Post with SAME wamid (Idempotency test)
        $response2 = $this->postJson('/api/whatsapp/webhook', $payload);
        $response2->assertStatus(200);

        // Count should not increase duplicate inbound messages
        $this->assertEquals($initialMessageCount, Message::count());
    }

    public function test_simulated_gateway_sends_without_network_call(): void
    {
        $gateway = app(SimulatedWhatsAppGateway::class);
        $this->assertEquals('simulation', $gateway->getMode());

        $result = $gateway->sendTextMessage('6281234567890', 'Pesan uji coba');
        $this->assertTrue($result['success']);
        $this->assertNotNull($result['message_id']);
        $this->assertStringStartsWith('SIM-TXT-', $result['message_id']);
    }

    public function test_production_gateway_blocks_sending_when_mode_is_simulation(): void
    {
        config(['whatsapp.mode' => 'simulation']);
        $gateway = new MetaWhatsAppCloudGateway();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('CRITICAL SAFETY BLOCK');

        $gateway->sendTextMessage('6281234567890', 'Pesan tidak boleh terkirim di simulasi');
    }
}

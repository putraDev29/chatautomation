<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\AI\AiSalesAssistantService;
use App\Services\AI\SimulatedAiProvider;
use Database\Seeders\BintangMobilDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiSalesAssistantTest extends TestCase
{
    use RefreshDatabase;

    protected SimulatedAiProvider $aiProvider;
    protected AiSalesAssistantService $aiAssistant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BintangMobilDemoSeeder::class);
        $this->aiProvider = app(SimulatedAiProvider::class);
        $this->aiAssistant = app(AiSalesAssistantService::class);
        $this->carSearchService = app(\App\Services\Cars\CarSearchService::class);
    }

    public function test_ai_understands_stock_inquiry(): void
    {
        $response = $this->aiProvider->process('Avanza 2022 ready gak min?');

        $this->assertStringContainsString('Avanza', $response['reply_text']);
        $this->assertStringContainsString('READY', $response['reply_text']);
        $this->assertStringContainsString('Rp 218.000.000', $response['reply_text']);
        $this->assertFalse($response['handover_required']);
    }

    public function test_ai_understands_media_photo_and_video_request(): void
    {
        $response = $this->aiProvider->process('Kirim vidio Innova Reborn dong');
        $this->assertNotNull($response['media_url']);
        $this->assertEquals('video', $response['media_type']);
        $this->assertEquals('getCarMedia', $response['tool_called']);
    }

    public function test_ai_understands_intercity_shipping_inquiry(): void
    {
        $response = $this->aiProvider->process('Bisa kirim ke Jakarta gak min? Biaya berapa?');

        $this->assertStringContainsString('Jakarta', $response['reply_text']);
        $this->assertStringContainsString('Towing', $response['reply_text']);
        $this->assertEquals('getDeliveryInformation', $response['tool_called']);
    }

    public function test_ai_triggers_handover_on_negotiation_or_test_drive(): void
    {
        $response = $this->aiProvider->process('Saya serius, bisa nego harga pas? Mau test drive besok');

        $this->assertTrue($response['handover_required']);
        $this->assertEquals('requestAdminHandover', $response['tool_called']);
        $this->assertStringContainsString('Admin Bintang Mobil', $response['reply_text']);
    }

    public function test_ai_handles_incoming_message_flow_in_conversation(): void
    {
        $customer = Customer::create([
            'name' => 'Pak Joko',
            'whatsapp_number' => '6281999888777',
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'BOT_ACTIVE',
            'is_bot_active' => true,
        ]);

        $incomingMsg = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'Brio matic ready gak?',
            'delivery_status' => 'delivered',
        ]);

        $outboundReply = $this->aiAssistant->handleIncomingMessage($conversation, $incomingMsg);

        $this->assertNotNull($outboundReply);
        $this->assertEquals('outbound', $outboundReply->direction);
        $this->assertEquals('bot', $outboundReply->sender_type);
        $this->assertStringContainsString('Brio', $outboundReply->message_body);
    }

    public function test_ai_handles_switching_car_inquiries_from_avanza_to_mobilio(): void
    {
        $customer = Customer::create([
            'name' => 'Budi',
            'whatsapp_number' => '6281234567890',
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'BOT_ACTIVE',
            'is_bot_active' => true,
        ]);

        // Step 1: Inquire about Avanza
        $msg1 = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'Avanza 2022 apa masih ready?',
            'delivery_status' => 'delivered',
        ]);
        $reply1 = $this->aiAssistant->handleIncomingMessage($conversation->fresh(), $msg1);
        $this->assertStringContainsString('Avanza', $reply1->message_body);
        $this->assertStringContainsString('READY', $reply1->message_body);
        $conversation->refresh();
        $this->assertNotNull($conversation->interested_car_id);
        $avanzaId = $conversation->interested_car_id;

        // Step 2: Switch to Mobilio
        $msg2 = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'Mobilio ada kak?',
            'delivery_status' => 'delivered',
        ]);
        $reply2 = $this->aiAssistant->handleIncomingMessage($conversation->fresh(), $msg2);
        $this->assertStringContainsString('Mobilio', $reply2->message_body);
        $this->assertStringContainsString('READY', $reply2->message_body);
        $conversation->refresh();
        $this->assertNotNull($conversation->interested_car_id);
        $this->assertNotEquals($avanzaId, $conversation->interested_car_id);

        // Step 3: Follow-up question about price (without mentioning Mobilio by name)
        $msg3 = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'Harganya berapa?',
            'delivery_status' => 'delivered',
        ]);
        $reply3 = $this->aiAssistant->handleIncomingMessage($conversation->fresh(), $msg3);
        $this->assertStringContainsString('Mobilio', $reply3->message_body);
        $this->assertStringContainsString('168.000.000', $reply3->message_body);

        // Step 4: Inquire about out-of-stock model (Cortez)
        $msg4 = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'Kalau Wuling Cortez ada?',
            'delivery_status' => 'delivered',
        ]);
        $reply4 = $this->aiAssistant->handleIncomingMessage($conversation->fresh(), $msg4);
        $this->assertStringContainsString('Cortez', $reply4->message_body);
        $this->assertStringContainsString('kosong', $reply4->message_body);
        $conversation->refresh();
        $this->assertNull($conversation->interested_car_id);
    }

    public function test_bot_auto_resumes_when_customer_asks_about_car_after_admin_reply(): void
    {
        $customer = Customer::create([
            'name' => 'Pak Hendra',
            'whatsapp_number' => '6285566778899',
        ]);

        $admin = \App\Models\User::first();

        // Conversation previously taken over by admin
        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'ADMIN_ACTIVE',
            'is_bot_active' => false,
            'is_admin_takeover' => true,
            'assigned_admin_id' => $admin->id,
        ]);

        // Customer message 1: pure talk to admin -> Bot remains silent
        $msgAdmin = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'Halo admin, sudah siap?',
            'delivery_status' => 'delivered',
        ]);
        $reply1 = $this->aiAssistant->handleIncomingMessage($conversation->fresh(), $msgAdmin);
        $this->assertNull($reply1);
        $conversation->refresh();
        $this->assertEquals('ADMIN_ACTIVE', $conversation->status);

        // Customer message 2: asks again about cars ("Mobilio apa ready?") -> Bot automatically resumes!
        $msgCar = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'Mobilio apa ready kak?',
            'delivery_status' => 'delivered',
        ]);
        $reply2 = $this->aiAssistant->handleIncomingMessage($conversation->fresh(), $msgCar);
        $this->assertNotNull($reply2);
        $this->assertEquals('bot', $reply2->sender_type);
        $this->assertStringContainsString('Mobilio', $reply2->message_body);

        $conversation->refresh();
        $this->assertTrue($conversation->is_bot_active);
        $this->assertFalse($conversation->is_admin_takeover);
        $this->assertEquals('BOT_ACTIVE', $conversation->status);
    }

    public function test_ai_understands_trade_in_tukar_tambah_inquiry(): void
    {
        // General trade-in inquiry with abbreviation TT
        $res1 = $this->aiProvider->process('Bisa TT mobil di sini kak?');
        $this->assertStringContainsString('TUKAR TAMBAH', $res1['reply_text']);
        $this->assertEquals('tradeInInquiry', $res1['tool_called']);

        // Trade-in to specific showroom car (Mobilio) with abbreviation TT
        $res2 = $this->aiProvider->process('Bisa TT ke Mobilio?');
        $this->assertStringContainsString('TUKAR TAMBAH', $res2['reply_text']);
        $this->assertStringContainsString('Mobilio', $res2['reply_text']);
        $this->assertEquals('tradeInInquiry', $res2['tool_called']);
        $this->assertNotNull($res2['interested_car_id']);
    }

    public function test_ai_understands_customer_car_specs_and_condition_trade_in_conversation(): void
    {
        // 1. Customer gives their car specs & condition
        $message1 = 'Mobil saya Xenia 2017 manual, km 60rb, plat L Surabaya, pajak hidup, kondisi mulus no PR siap pakai';
        $res1 = $this->aiProvider->process($message1, ['customer_name' => 'Bambang']);

        $this->assertEquals('customerCarTradeInAppraisal', $res1['tool_called']);
        $this->assertStringContainsString('Xenia', $res1['reply_text']);
        $this->assertStringContainsString('2017', $res1['reply_text']);
        $this->assertStringContainsString('Manual', $res1['reply_text']);
        $this->assertStringContainsString('60.000 KM', $res1['reply_text']);
        $this->assertStringContainsString('Plat L', $res1['reply_text']);
        $this->assertStringContainsString('Pajak Hidup', $res1['reply_text']);
        $this->assertStringContainsString('Mulus', $res1['reply_text']);
        $this->assertStringContainsString('Tanpa PR', $res1['reply_text']);

        // 2. Customer mentions their car AND target showroom car for trade-in
        $message2 = 'Mau TT mobilio ini sama brio 2019 matic saya, km 30rb, mulus terawat, ss lengkap';
        $res2 = $this->aiProvider->process($message2, ['customer_name' => 'Rina']);

        $this->assertEquals('customerCarTradeInAppraisal', $res2['tool_called']);
        $this->assertStringContainsString('Brio', $res2['reply_text']);
        $this->assertStringContainsString('Mobilio', $res2['reply_text']);
        $this->assertStringContainsString('Matic', $res2['reply_text']);
        $this->assertStringContainsString('30.000 KM', $res2['reply_text']);
        $this->assertStringContainsString('Surat-Surat (SS) Lengkap', $res2['reply_text']);
    }

    public function test_ai_understands_automotive_slang_and_abbreviations(): void
    {
        // 1. Transmission abbreviations MT and AT
        $parsedMt = $this->carSearchService->parseQueryFromText('Avanza MT ada?');
        $this->assertEquals('Manual', $parsedMt['transmission']);
        $this->assertEquals('Avanza', $parsedMt['model']);

        $parsedAt = $this->carSearchService->parseQueryFromText('Brio AT ready?');
        $this->assertEquals('Otomatis', $parsedAt['transmission']);
        $this->assertEquals('Brio', $parsedAt['model']);

        // 2. DP / TDP abbreviation
        $resDp = $this->aiProvider->process('DP berapa untuk kredit?');
        $this->assertEquals('creditFaqInquiry', $resDp['tool_called']);

        // 3. SS (Surat-surat) & BBN abbreviations
        $resSs = $this->aiProvider->process('Apakah SS lengkap dan bisa bantu BBN?');
        $this->assertEquals('documentsFaqInquiry', $resSs['tool_called']);
        $this->assertStringContainsString('Balik Nama (BBN)', $resSs['reply_text']);

        // 4. No PR & Garansi abbreviations
        $resWarranty = $this->aiProvider->process('Kondisi unit apakah no PR dan bergaransi?');
        $this->assertEquals('warrantyFaqInquiry', $resWarranty['tool_called']);
    }

    public function test_ai_understands_car_sales_faq_inquiries(): void
    {
        // 1. Showroom buys customer car
        $resBuy = $this->aiProvider->process('Saya mau jual mobil bekas saya ke showroom bisa?');
        $this->assertStringContainsString('BELI TUNAI', $resBuy['reply_text']);
        $this->assertEquals('sellCarToShowroom', $resBuy['tool_called']);

        // 2. Credit requirements
        $resCredit = $this->aiProvider->process('Syarat pengajuan kredit dan leasing apa saja?');
        $this->assertStringContainsString('Persyaratan Dokumen', $resCredit['reply_text']);
        $this->assertStringContainsString('BCA Finance', $resCredit['reply_text']);
        $this->assertEquals('creditFaqInquiry', $resCredit['tool_called']);

        // 3. Warranty & Inspection
        $resWarranty = $this->aiProvider->process('Apakah mobil di sini bergaransi bebas banjir dan tabrak?');
        $this->assertStringContainsString('Bebas Tabrakan Berat', $resWarranty['reply_text']);
        $this->assertStringContainsString('Bebas Rendaman Banjir', $resWarranty['reply_text']);
        $this->assertEquals('warrantyFaqInquiry', $resWarranty['tool_called']);

        // 4. Documents & Name transfer (BBN)
        $resDoc = $this->aiProvider->process('Bisa bantu proses balik nama dan mutasi surat?');
        $this->assertStringContainsString('Balik Nama (BBN)', $resDoc['reply_text']);
        $this->assertEquals('documentsFaqInquiry', $resDoc['tool_called']);

        // 5. Booking lock procedure
        $resBooking = $this->aiProvider->process('Cara booking dan tanda jadi berapa? Apakah uang kembali?');
        $this->assertStringContainsString('Refundable', $resBooking['reply_text']);
        $this->assertEquals('bookingFaqInquiry', $resBooking['tool_called']);
    }

    public function test_bot_remains_active_when_admin_replies_and_answers_subsequent_customer_questions(): void
    {
        $customer = Customer::create([
            'name' => 'Budi Santoso',
            'whatsapp_number' => '6281234567899',
            'communication_status' => 'ACTIVE',
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'ADMIN_ACTIVE',
            'is_bot_active' => false,
            'is_admin_takeover' => true,
        ]);

        // 1. Admin sends reply from WhatsApp Business (HP B) via bridge-admin-message
        $response = $this->postJson(route('api.whatsapp.bridge.admin-message'), [
            'phone_number' => $customer->whatsapp_number,
            'message' => 'Halo Pak Budi, unit masih ready ya di showroom.',
            'wa_message_id' => 'ADMIN_MSG_TEST_001',
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true, 'is_bot_active' => true]);

        // Check conversation state: Bot MUST be active!
        $conversation->refresh();
        $this->assertTrue($conversation->is_bot_active);
        $this->assertFalse($conversation->is_admin_takeover);
        $this->assertEquals('BOT_ACTIVE', $conversation->status);

        // 2. Customer asks another general question without needing admin to press activate bot button
        $incomingMsg = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'Showroom buka sampai jam berapa ya mas?',
            'delivery_status' => 'delivered',
        ]);

        $botReply = $this->aiAssistant->handleIncomingMessage($conversation->fresh(), $incomingMsg);
        $this->assertNotNull($botReply);
        $this->assertEquals('bot', $botReply->sender_type);
        $this->assertStringContainsString('08.30', $botReply->message_body);
    }

    public function test_chat_and_messages_can_be_deleted_via_bridge_sync_and_dashboard(): void
    {
        $customer = Customer::create([
            'name' => 'Hapus Tester',
            'whatsapp_number' => '6289999888877',
            'communication_status' => 'ACTIVE',
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'BOT_ACTIVE',
            'is_bot_active' => true,
        ]);

        $msg1 = Message::create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => 'MSG_DEL_001',
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'Pesan 1 akan dihapus',
        ]);

        $msg2 = Message::create([
            'conversation_id' => $conversation->id,
            'wa_message_id' => 'MSG_DEL_002',
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'Pesan 2',
        ]);

        // 1. Delete single message via bridge-delete
        $delMsgResp = $this->postJson(route('api.whatsapp.bridge.delete'), [
            'type' => 'message',
            'ids' => ['MSG_DEL_001'],
        ]);
        $delMsgResp->assertOk();
        $this->assertDatabaseMissing('wa_messages', ['id' => $msg1->id]);
        $this->assertDatabaseHas('wa_messages', ['id' => $msg2->id]);

        // 2. Delete entire chat via bridge-delete
        $delChatResp = $this->postJson(route('api.whatsapp.bridge.delete'), [
            'type' => 'chat',
            'phone_number' => '6289999888877',
        ]);
        $delChatResp->assertOk();
        $this->assertDatabaseMissing('wa_conversations', ['id' => $conversation->id]);
        $this->assertDatabaseMissing('wa_messages', ['id' => $msg2->id]);

        // 3. Test Dashboard destroy route
        $user = \App\Models\User::factory()->create();
        $convNew = Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'BOT_ACTIVE',
            'is_bot_active' => true,
        ]);

        $dashDelResp = $this->actingAs($user)->delete(route('admin.conversations.destroy', $convNew->id));
        $dashDelResp->assertRedirect(route('admin.conversations.index'));
        $this->assertDatabaseMissing('wa_conversations', ['id' => $convNew->id]);
    }

    public function test_media_is_only_sent_when_requested_and_uses_openable_image(): void
    {
        // 1. Customer asks about stock without requesting photo -> NO MEDIA
        $noMediaResponse = $this->aiProvider->process('Avanza 2022 ready gak min?');
        $this->assertNull($noMediaResponse['media_url'], 'Foto tidak boleh dikirim jika customer tidak meminta');

        // 2. Customer asks about trade-in without requesting photo -> NO MEDIA
        $tradeInResponse = $this->aiProvider->process('Bisa tukar tambah Avanza ke Mobilio?');
        $this->assertNull($tradeInResponse['media_url'], 'Foto tidak boleh dikirim saat inquiry TT umum tanpa permintaan foto');

        // 3. Customer explicitly requests photo -> MEDIA SENT with openable .jpg file
        $photoResponse = $this->aiProvider->process('Bisa minta foto unit Avanza?');
        $this->assertNotNull($photoResponse['media_url'], 'Foto harus dikirim ketika customer meminta foto');
        $this->assertStringEndsWith('.jpg', $photoResponse['media_url']);

        // Check that the file actually exists on disk in public folder so WhatsApp can open it
        $cleanPath = ltrim(parse_url($photoResponse['media_url'], PHP_URL_PATH), '/');
        $this->assertFileExists(public_path($cleanPath), 'File foto harus benar-benar ada di public directory');

        // 4. Customer asks for interior photo -> INTERIOR PHOTO SENT
        $interiorResponse = $this->aiProvider->process('Ada foto interior Brio gak?');
        $this->assertNotNull($interiorResponse['media_url']);
        $this->assertStringContainsString('interior.jpg', $interiorResponse['media_url']);
        $this->assertFileExists(public_path('demo-cars/interior.jpg'));
    }

    public function test_multi_admin_sales_with_distinct_whatsapp_numbers(): void
    {
        $admin1 = \App\Models\User::create([
            'name' => 'Admin Showroom',
            'email' => 'admin.test1@bintangmobil.test',
            'whatsapp_number' => '6285729905390',
            'role' => 'superadmin',
            'password' => bcrypt('secret123'),
        ]);

        $admin2 = \App\Models\User::create([
            'name' => 'Sales Counter Dewi',
            'email' => 'dewi.test2@bintangmobil.test',
            'whatsapp_number' => '6281234567892',
            'role' => 'sales',
            'password' => bcrypt('secret123'),
        ]);

        $this->assertNotEquals($admin1->whatsapp_number, $admin2->whatsapp_number);
        $this->assertEquals('superadmin', $admin1->role);
        $this->assertEquals('sales', $admin2->role);
        $this->assertDatabaseHas('users', ['email' => 'admin.test1@bintangmobil.test', 'whatsapp_number' => '6285729905390']);
        $this->assertDatabaseHas('users', ['email' => 'dewi.test2@bintangmobil.test', 'whatsapp_number' => '6281234567892']);
    }

    public function test_multi_session_bridge_message_isolates_and_assigns_admin(): void
    {
        // 1. Create 2 distinct sales users and 1 superadmin
        $superadmin = \App\Models\User::create([
            'name' => 'Owner Showroom',
            'email' => 'owner@bintangmobil.test',
            'role' => 'superadmin',
            'password' => bcrypt('secret123'),
        ]);

        $salesA = \App\Models\User::create([
            'name' => 'Sales Andi',
            'email' => 'andi@bintangmobil.test',
            'role' => 'sales',
            'password' => bcrypt('secret123'),
        ]);

        $salesB = \App\Models\User::create([
            'name' => 'Sales Dewi',
            'email' => 'dewi@bintangmobil.test',
            'role' => 'sales',
            'password' => bcrypt('secret123'),
        ]);

        \App\Models\WaAdminSession::create([
            'user_id' => $salesA->id,
            'session_id' => 'session_andi',
            'session_name' => 'Sales Andi',
            'status' => 'connected',
        ]);

        \App\Models\WaAdminSession::create([
            'user_id' => $salesB->id,
            'session_id' => 'session_dewi',
            'session_name' => 'Sales Dewi',
            'status' => 'connected',
        ]);

        // 2. Customer 1 chats to Andi's WhatsApp number / barcode
        $respA = $this->postJson(route('api.whatsapp.bridge.message'), [
            'phone_number' => '628111222333',
            'customer_name' => 'Customer Mobilio',
            'message' => 'Mobilio 2019 masih ada Mas Andi?',
            'session_id' => 'session_andi',
        ]);
        $respA->assertOk();
        $this->assertTrue($respA->json('success'));

        // Verify conversation is tagged with Sales Andi!
        $convA = Conversation::where('wa_session_id', 'session_andi')->first();
        $this->assertNotNull($convA);
        $this->assertEquals($salesA->id, $convA->assigned_admin_id);

        // 3. Customer 2 chats to Dewi's WhatsApp number / barcode
        $respB = $this->postJson(route('api.whatsapp.bridge.message'), [
            'phone_number' => '628444555666',
            'customer_name' => 'Customer Brio',
            'message' => 'Brio kuning masih ada Mbak Dewi?',
            'session_id' => 'session_dewi',
        ]);
        $respB->assertOk();
        $this->assertTrue($respB->json('success'));

        $convB = Conversation::where('wa_session_id', 'session_dewi')->first();
        $this->assertNotNull($convB);
        $this->assertEquals($salesB->id, $convB->assigned_admin_id);

        // 4. Test Chat Isolation on Web Dashboard
        // When Sales Andi logs in: only sees Customer Mobilio ($convA), DOES NOT see Customer Brio ($convB)
        $andiView = $this->actingAs($salesA)->get(route('admin.conversations.index'));
        $andiView->assertOk();
        $andiConversations = $andiView->viewData('conversations');
        $this->assertTrue($andiConversations->contains('id', $convA->id));
        $this->assertFalse($andiConversations->contains('id', $convB->id));

        // When Sales Dewi logs in: only sees Customer Brio ($convB), DOES NOT see Customer Mobilio ($convA)
        $dewiView = $this->actingAs($salesB)->get(route('admin.conversations.index'));
        $dewiView->assertOk();
        $dewiConversations = $dewiView->viewData('conversations');
        $this->assertTrue($dewiConversations->contains('id', $convB->id));
        $this->assertFalse($dewiConversations->contains('id', $convA->id));

        // When Superadmin logs in: can see both!
        $superView = $this->actingAs($superadmin)->get(route('admin.conversations.index'));
        $superView->assertOk();
        $superConversations = $superView->viewData('conversations');
        $this->assertTrue($superConversations->contains('id', $convA->id));
        $this->assertTrue($superConversations->contains('id', $convB->id));
    }

    public function test_ai_displays_all_ready_cars_when_customer_asks_for_ready_list(): void
    {
        $response = $this->aiProvider->process('mobil apa saja yg ready tampilkan semua listnya');

        $this->assertEquals('listAllAvailableCars', $response['tool_called']);
        $this->assertStringContainsString('DAFTAR LENGKAP', $response['reply_text']);
        $this->assertStringContainsString('Avanza', $response['reply_text']);
        $this->assertStringContainsString('Innova Reborn', $response['reply_text']);
        $this->assertStringContainsString('Brio', $response['reply_text']);
        $this->assertStringContainsString('Xpander', $response['reply_text']);
        $this->assertNull($response['media_url']); // no photo requested
    }

    public function test_ai_handles_inquiry_for_specific_year_by_offering_available_alternative_year(): void
    {
        // Customer asks for Innova Reborn 2022, but showroom has 2021
        $response = $this->aiProvider->process('innova reborn 2022 ada gak?');

        $this->assertEquals('suggestSameModelAlternativeYear', $response['tool_called']);
        $this->assertStringContainsString('Innova Reborn tahun 2022', $response['reply_text']);
        $this->assertStringContainsString('belum ada', $response['reply_text']);
        $this->assertStringContainsString('READY saat ini', $response['reply_text']);
        $this->assertStringContainsString('2021', $response['reply_text']);
        $this->assertNull($response['media_url']); // customer didn't ask for photos

        // When customer explicitly asks for photo of Innova Reborn 2022:
        $photoResponse = $this->aiProvider->process('minta foto innova reborn 2022 dong min');
        $this->assertStringContainsString('belum ada', $photoResponse['reply_text']);
        $this->assertStringContainsString('READY saat ini', $photoResponse['reply_text']);
        $this->assertNotNull($photoResponse['media_url']); // photo of ready 2021 unit is attached
        $this->assertEquals('image', $photoResponse['media_type']);
    }

    public function test_ai_synchronizes_color_inquiry_with_previously_asked_car(): void
    {
        $customer = Customer::create([
            'name' => 'Pak Bambang',
            'whatsapp_number' => '6281777666555',
        ]);

        $conversation = Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'BOT_ACTIVE',
            'is_bot_active' => true,
        ]);

        // 1. First message: customer asks about Innova Reborn
        $msg1 = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'Innova Reborn ready gak min?',
        ]);

        $reply1 = $this->aiAssistant->handleIncomingMessage($conversation, $msg1);
        $this->assertNotNull($reply1);
        $conversation->refresh();
        $this->assertNotNull($conversation->interested_car_id);
        $this->assertEquals('Innova Reborn', $conversation->interestedCar->model);

        // 2. Second message: customer only asks: "warna apa saja yang ready?"
        $msg2 = Message::create([
            'conversation_id' => $conversation->id,
            'direction' => 'inbound',
            'sender_type' => 'customer',
            'message_body' => 'warna apa saja yang ready?',
        ]);

        $reply2 = $this->aiAssistant->handleIncomingMessage($conversation, $msg2);
        $this->assertNotNull($reply2);
        $this->assertStringContainsString('Innova Reborn', $reply2->message_body);
        $this->assertStringContainsString('Hitam Metalik', $reply2->message_body);
        $this->assertEquals($conversation->interested_car_id, $reply2->conversation->interested_car_id);
    }

    public function test_ai_handles_color_inquiry_when_car_is_specified_in_message(): void
    {
        $response = $this->aiProvider->process('brio warna apa saja yang ready?');

        $this->assertEquals('getCarColorAvailability', $response['tool_called']);
        $this->assertStringContainsString('Brio', $response['reply_text']);
        $this->assertStringContainsString('Kuning Carnival', $response['reply_text']);
    }

    public function test_ai_handles_general_color_inquiry_when_no_car_is_previously_asked(): void
    {
        $response = $this->aiProvider->process('warna apa saja yang ready?');

        $this->assertEquals('getAvailableColorsOverview', $response['tool_called']);
        $this->assertStringContainsString('Putih', $response['reply_text']);
        $this->assertStringContainsString('Hitam', $response['reply_text']);
        $this->assertStringContainsString('Silver', $response['reply_text']);
        $this->assertStringContainsString('tipe mobil apa', $response['reply_text']);
    }

    public function test_ai_handles_specific_color_query_with_active_car(): void
    {
        $innova = \App\Models\Car::where('model', 'Innova Reborn')->first();

        // Customer asks if Innova has white color, but stock has Hitam Metalik
        $response = $this->aiProvider->process('ada warna putih gak?', [
            'customer_name' => 'Kak Andi',
            'interested_car_id' => $innova->id,
        ]);

        $this->assertEquals('getCarColorAvailability', $response['tool_called']);
        $this->assertStringContainsString('Innova Reborn', $response['reply_text']);
        $this->assertStringContainsString('belum ready', $response['reply_text']);
        $this->assertStringContainsString('Hitam Metalik', $response['reply_text']);
    }
}

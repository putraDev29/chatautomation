<?php

use App\Http\Controllers\Api\WhatsAppWebhookController;
use App\Services\AI\AiSalesAssistantService;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

// Official Meta WhatsApp Cloud API Webhook
Route::get('/whatsapp/webhook', [WhatsAppWebhookController::class, 'verify'])->name('api.whatsapp.webhook.verify');
Route::post('/whatsapp/webhook', [WhatsAppWebhookController::class, 'handle'])->name('api.whatsapp.webhook.handle');

// Direct Simulation API for local testing
Route::post('/whatsapp/simulate', function (Request $request, WhatsAppMessageService $msgService, AiSalesAssistantService $aiAssistant) {
    $validated = $request->validate([
        'phone_number' => 'required|string',
        'customer_name' => 'nullable|string',
        'message' => 'required|string',
    ]);

    $ingest = $msgService->ingestIncomingMessage(
        fromNumber: $validated['phone_number'],
        customerName: $validated['customer_name'] ?? 'Tester',
        messageBody: $validated['message'],
        waMessageId: 'SIM-API-' . uniqid(),
        messageType: 'text',
        metadata: ['simulated_source' => 'api_simulate']
    );

    $botReply = $aiAssistant->handleIncomingMessage($ingest['conversation'], $ingest['message']);

    return response()->json([
        'success' => true,
        'conversation_id' => $ingest['conversation']->id,
        'conversation_status' => $ingest['conversation']->status,
        'is_bot_active' => $ingest['conversation']->is_bot_active,
        'customer_message' => $ingest['message']->message_body,
        'bot_reply' => $botReply ? [
            'body' => $botReply->message_body,
            'media_url' => $botReply->media_url,
            'media_type' => $botReply->message_type,
            'delivery_status' => $botReply->delivery_status,
        ] : null,
    ]);
})->name('api.whatsapp.simulate');

// Multi-Admin WhatsApp Web Bridge List
Route::get('/whatsapp/bridge-admins', function () {
    $sessions = \App\Models\WaAdminSession::with('user')->get()->map(function ($s) {
        return [
            'session_id' => $s->session_id,
            'user_id' => $s->user_id,
            'name' => $s->user->name ?? $s->session_name,
            'role' => $s->user->role ?? 'sales',
            'phone_number' => $s->phone_number,
            'status' => $s->status,
        ];
    });
    return response()->json($sessions);
});

// Local WhatsApp Web Bridge (Node.js QR Gateway)
Route::post('/whatsapp/bridge-message', function (Request $request, WhatsAppMessageService $msgService, AiSalesAssistantService $aiAssistant) {
    $validated = $request->validate([
        'phone_number' => 'required|string',
        'customer_name' => 'nullable|string',
        'message' => 'required|string',
        'wa_message_id' => 'nullable|string',
        'session_id' => 'nullable|string',
    ]);

    $waMessageId = ($validated['wa_message_id'] ?? null) ?: ('BRIDGE-' . uniqid());
    $sessionId = $validated['session_id'] ?? 'admin_1';
    
    $adminSession = \App\Models\WaAdminSession::where('session_id', $sessionId)->first();
    $assignedAdminId = $adminSession?->user_id;

    $ingest = $msgService->ingestIncomingMessage(
        fromNumber: $validated['phone_number'],
        customerName: $validated['customer_name'] ?? 'Customer',
        messageBody: $validated['message'],
        waMessageId: $waMessageId,
        messageType: 'text',
        metadata: [
            'source' => 'whatsapp_web_bridge_qr',
            'session_id' => $sessionId,
            'assigned_admin_id' => $assignedAdminId,
        ]
    );

    if ($ingest['is_duplicate']) {
        return response()->json(['success' => true, 'should_reply' => false, 'reason' => 'duplicate']);
    }

    $conversation = $ingest['conversation'];
    $incomingMsg = $ingest['message'];

    $botReply = $aiAssistant->handleIncomingMessage($conversation, $incomingMsg);

    if (!$botReply) {
        return response()->json([
            'success' => true,
            'should_reply' => false,
            'reason' => 'bot_inactive_or_admin_takeover',
            'conversation_status' => $conversation->status,
        ]);
    }

    return response()->json([
        'success' => true,
        'should_reply' => true,
        'reply_text' => $botReply->message_body,
        'media_url' => $botReply->media_url ? (str_starts_with($botReply->media_url, 'http') ? $botReply->media_url : url($botReply->media_url)) : null,
        'media_type' => $botReply->message_type,
        'conversation_status' => $conversation->status,
    ]);
})->name('api.whatsapp.bridge.message');

// Live QR Status Sync between Node.js Bridge and Laravel Web Dashboard (Multi-Session)
Route::post('/whatsapp/bridge-status', function (Request $request) {
    $sessionId = $request->input('session_id', 'admin_1');
    $status = $request->input('status', 'disconnected');
    $qrCode = $request->input('qr_code');
    $phone = $request->input('phone');
    $name = $request->input('name');

    $session = \App\Models\WaAdminSession::where('session_id', $sessionId)->first();
    if ($session) {
        $session->update([
            'status' => $status,
            'qr_code' => $qrCode,
            'phone_number' => $phone ?: $session->phone_number,
            'last_active_at' => now(),
        ]);
    }

    $sessionData = [
        'session_id' => $sessionId,
        'status' => $status,
        'qr_code' => $qrCode,
        'phone' => $phone,
        'name' => $name,
        'updated_at' => now()->toIso8601String(),
    ];

    \Illuminate\Support\Facades\Cache::put("wa_bridge_status_{$sessionId}", $sessionData, now()->addMinutes(30));

    // Also update legacy/default key for single-status backwards compatibility
    if ($sessionId === 'admin_1' || $request->input('is_primary', false)) {
        \Illuminate\Support\Facades\Cache::put('wa_bridge_status', $sessionData, now()->addMinutes(30));
    }

    return response()->json(['success' => true]);
});

Route::get('/whatsapp/bridge-status', function (Request $request) {
    $requestedSessionId = $request->query('session_id');

    if ($requestedSessionId) {
        $session = \App\Models\WaAdminSession::where('session_id', $requestedSessionId)->first();
        $cached = \Illuminate\Support\Facades\Cache::get("wa_bridge_status_{$requestedSessionId}", []);
        return response()->json([
            'session_id' => $requestedSessionId,
            'status' => $cached['status'] ?? ($session?->status ?? 'disconnected'),
            'qr_code' => $cached['qr_code'] ?? $session?->qr_code,
            'phone' => $cached['phone'] ?? $session?->phone_number,
            'name' => $session?->session_name,
            'updated_at' => $cached['updated_at'] ?? $session?->last_active_at?->toIso8601String(),
        ]);
    }

    $allSessions = \App\Models\WaAdminSession::with('user')->get()->map(function ($s) {
        $cache = \Illuminate\Support\Facades\Cache::get("wa_bridge_status_{$s->session_id}", []);
        return [
            'session_id' => $s->session_id,
            'user_id' => $s->user_id,
            'name' => $s->user->name ?? $s->session_name,
            'role' => $s->user->role ?? 'sales',
            'phone' => $cache['phone'] ?? $s->phone_number,
            'status' => $cache['status'] ?? $s->status,
            'qr_code' => $cache['qr_code'] ?? $s->qr_code,
            'updated_at' => $cache['updated_at'] ?? $s->last_active_at?->toIso8601String(),
        ];
    });

    $primaryStatus = \Illuminate\Support\Facades\Cache::get('wa_bridge_status', [
        'status' => 'offline',
        'qr_code' => null,
        'phone' => null,
        'name' => null,
        'updated_at' => null,
    ]);

    // If auto_start is requested and bridge is offline or disconnected, trigger background launcher
    if ($request->boolean('auto_start', false) && ($primaryStatus['status'] === 'offline' || $primaryStatus['status'] === 'disconnected')) {
        $launchResult = \App\Services\WhatsApp\WhatsAppBridgeManager::startBridgeInBackground();
        $primaryStatus['auto_starting'] = true;
        $primaryStatus['launch_info'] = $launchResult['message'] ?? 'Memulai bridge...';
    }

    return response()->json([
        'status' => $primaryStatus['status'],
        'qr_code' => $primaryStatus['qr_code'],
        'phone' => $primaryStatus['phone'],
        'name' => $primaryStatus['name'],
        'updated_at' => $primaryStatus['updated_at'],
        'sessions' => $allSessions,
    ]);
});

// Endpoint to manually or programmatically launch the bridge
Route::post('/whatsapp/bridge-start', function () {
    $result = \App\Services\WhatsApp\WhatsAppBridgeManager::startBridgeInBackground();
    return response()->json($result);
});

// Admin message sent directly from WhatsApp Business (HP B) - Keeps Bot Active
Route::post('/whatsapp/bridge-admin-message', function (Request $request) {
    $validated = $request->validate([
        'phone_number' => 'required|string',
        'message' => 'required|string',
        'wa_message_id' => 'nullable|string',
        'session_id' => 'nullable|string',
    ]);

    $normalizedNumber = \App\Models\Customer::formatWhatsAppNumber($validated['phone_number']);
    $sessionId = $validated['session_id'] ?? null;
    $adminSession = $sessionId ? \App\Models\WaAdminSession::where('session_id', $sessionId)->first() : null;
    $adminUserId = $adminSession?->user_id;

    $customer = \App\Models\Customer::firstOrCreate(
        ['whatsapp_number' => $normalizedNumber],
        [
            'name' => "Customer {$normalizedNumber}",
            'customer_source' => 'WHATSAPP',
            'communication_status' => 'ACTIVE',
        ]
    );

    $customer->update(['last_interaction_at' => now()]);

    $conversation = \App\Models\Conversation::where('customer_id', $customer->id)->latest()->first();

    if (!$conversation) {
        $conversation = \App\Models\Conversation::create([
            'customer_id' => $customer->id,
            'status' => 'BOT_ACTIVE',
            'is_bot_active' => true,
            'is_admin_takeover' => false,
            'assigned_admin_id' => $adminUserId,
            'wa_session_id' => $sessionId,
            'interest_level' => 'MEDIUM',
            'last_message_at' => now(),
        ]);
    }

    $waMessageId = $validated['wa_message_id'] ?: ('ADMIN-' . uniqid());

    // Record outbound admin message
    $message = \App\Models\Message::updateOrCreate(
        ['wa_message_id' => $waMessageId],
        [
            'conversation_id' => $conversation->id,
            'direction' => 'outbound',
            'sender_type' => 'admin',
            'message_type' => 'text',
            'message_body' => $validated['message'],
            'delivery_status' => 'delivered',
            'metadata' => [
                'session_id' => $sessionId,
                'admin_id' => $adminUserId,
            ],
            'sent_at' => now(),
        ]
    );

    // CRITICAL: Bot tetap aktif ketika admin membalas, sehingga pertanyaan customer berikutnya langsung dijawab bot!
    $updates = [
        'is_bot_active' => true,
        'is_admin_takeover' => false,
        'status' => 'BOT_ACTIVE',
        'last_message_at' => now(),
    ];
    if ($adminUserId && empty($conversation->assigned_admin_id)) {
        $updates['assigned_admin_id'] = $adminUserId;
    }
    if ($sessionId && empty($conversation->wa_session_id)) {
        $updates['wa_session_id'] = $sessionId;
    }
    $conversation->update($updates);

    \App\Models\HandoverLog::create([
        'conversation_id' => $conversation->id,
        'trigger_type' => 'MANUAL_ADMIN',
        'action' => 'ADMIN_REPLIED_BOT_ACTIVE',
        'reason' => 'Admin membalas langsung di WhatsApp Bisnis. Bot tetap aktif untuk pertanyaan berikutnya.',
    ]);

    return response()->json([
        'success' => true,
        'conversation_id' => $conversation->id,
        'is_bot_active' => true,
        'message_id' => $message->id,
    ]);
})->name('api.whatsapp.bridge.admin-message');

// Sync Chat / Message Deletion from WhatsApp Business (HP B)
Route::post('/whatsapp/bridge-delete', function (Request $request) {
    $type = $request->input('type', 'message');
    $ids = $request->input('ids', []);
    $phone = $request->input('phone_number');
    $jid = $request->input('jid');

    if ($type === 'message' && !empty($ids)) {
        $deletedCount = \App\Models\Message::whereIn('wa_message_id', (array) $ids)->delete();
        return response()->json([
            'success' => true,
            'action' => 'messages_deleted',
            'count' => $deletedCount,
        ]);
    }

    if ($type === 'chat' || !empty($phone) || !empty($jid)) {
        $conversations = collect();

        if (!empty($phone)) {
            $formattedPhone = \App\Models\Customer::formatWhatsAppNumber($phone);
            $cleanPhone = preg_replace('/[^0-9]/', '', $phone);

            $customer = \App\Models\Customer::where('whatsapp_number', $formattedPhone)
                ->orWhere('whatsapp_number', $cleanPhone)
                ->orWhere('whatsapp_number', 'LIKE', "%{$cleanPhone}%")
                ->first();

            if ($customer) {
                $conversations = \App\Models\Conversation::where('customer_id', $customer->id)->get();
            }
        }

        if ($conversations->isEmpty() && !empty($jid)) {
            $conversations = \App\Models\Conversation::where('wa_conversation_id', $jid)->get();
        }

        $count = $conversations->count();
        foreach ($conversations as $conv) {
            $conv->delete();
        }

        return response()->json([
            'success' => true,
            'action' => 'conversations_deleted',
            'count' => $count,
        ]);
    }

    return response()->json(['success' => false, 'message' => 'Tipe delete tidak valid'], 400);
})->name('api.whatsapp.bridge.delete');




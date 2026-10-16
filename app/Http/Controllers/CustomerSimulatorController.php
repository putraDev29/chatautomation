<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Message;
use App\Services\AI\AiSalesAssistantService;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Http\Request;

class CustomerSimulatorController extends Controller
{
    public function index(Request $request)
    {
        $phone = $request->query('phone', '6281234567801');
        $customer = Customer::firstOrCreate(
            ['whatsapp_number' => $phone],
            ['name' => 'Budi Santoso', 'customer_source' => 'WHATSAPP']
        );

        $conversation = Conversation::with(['messages', 'interestedCar'])
            ->where('customer_id', $customer->id)
            ->latest()
            ->first();

        if (!$conversation) {
            $conversation = Conversation::create([
                'customer_id' => $customer->id,
                'status' => 'BOT_ACTIVE',
                'is_bot_active' => true,
            ]);
        }

        $allCustomers = Customer::all();

        return view('simulator', compact('customer', 'conversation', 'allCustomers'));
    }

    public function sendMessage(Request $request, WhatsAppMessageService $msgService, AiSalesAssistantService $aiAssistant)
    {
        $request->validate([
            'phone' => 'required|string',
            'customer_name' => 'nullable|string',
            'message' => 'required|string',
        ]);

        $phone = $request->input('phone');
        $name = $request->input('customer_name') ?: 'Customer ' . substr($phone, -4);
        $body = $request->input('message');

        // Ingest as real incoming WhatsApp message
        $result = $msgService->ingestIncomingMessage(
            fromNumber: $phone,
            customerName: $name,
            messageBody: $body,
            waMessageId: 'SIM-PHONE-' . uniqid(),
            messageType: 'text',
            metadata: ['simulator_client' => 'web_phone_mockup']
        );

        $conversation = $result['conversation'];
        $incomingMsg = $result['message'];

        // Trigger AI Assistant (will only reply if bot is active and not takeover)
        $botReply = $aiAssistant->handleIncomingMessage($conversation, $incomingMsg);

        return redirect()->route('simulator', ['phone' => $phone]);
    }
}

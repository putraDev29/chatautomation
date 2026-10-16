<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\WhatsAppIntegration;
use App\Services\AI\AiSalesAssistantService;
use App\Services\Conversations\ConversationHandoverService;
use App\Services\Leads\LeadManagementService;
use App\Services\WhatsApp\WhatsAppMessageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConversationController extends Controller
{
    public function __construct(
        protected WhatsAppMessageService $messageService,
        protected ConversationHandoverService $handoverService,
        protected AiSalesAssistantService $aiAssistant,
        protected LeadManagementService $leadService
    ) {}

    public function index(Request $request)
    {
        $statusFilter = $request->query('status');
        $adminFilter = $request->query('admin_id');
        $search = $request->query('q');
        $selectedId = $request->query('selected');

        $user = Auth::user();

        $query = Conversation::with(['customer', 'interestedCar', 'latestMessage', 'assignedAdmin'])
            ->orderBy('last_message_at', 'desc');

        // CHAT ISOLATION:
        // Jika sales biasa (bukan superadmin), HANYA melihat percakapan miliknya sendiri
        if ($user && !$user->isSuperAdmin()) {
            $query->where('assigned_admin_id', $user->id);
        } elseif (!empty($adminFilter)) {
            // Superadmin dapat memilih melihat percakapan admin tertentu
            $query->where('assigned_admin_id', $adminFilter);
        }

        if (!empty($statusFilter)) {
            $query->where('status', $statusFilter);
        }

        if (!empty($search)) {
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                  ->orWhere('whatsapp_number', 'LIKE', "%{$search}%");
            });
        }

        $conversations = $query->paginate(20)->withQueryString();

        // Selected active conversation
        $activeConversation = null;
        if ($selectedId) {
            $activeQuery = Conversation::with(['customer', 'interestedCar.media', 'messages', 'handoverLogs.performedBy']);
            if ($user && !$user->isSuperAdmin()) {
                $activeQuery->where('assigned_admin_id', $user->id);
            }
            $activeConversation = $activeQuery->find($selectedId);
        }

        if (!$activeConversation && $conversations->isNotEmpty()) {
            $activeConversation = Conversation::with(['customer', 'interestedCar.media', 'messages', 'handoverLogs.performedBy'])->find($conversations->first()->id);
        }

        $activeLead = null;
        if ($activeConversation) {
            $activeLead = Lead::where('customer_id', $activeConversation->customer_id)->first();
        }

        $availableCars = Car::where('stock_status', 'TERSEDIA')->get();
        $integration = WhatsAppIntegration::current();
        $adminList = \App\Models\User::orderBy('name')->get();

        return view('admin.conversations.index', compact(
            'conversations',
            'activeConversation',
            'activeLead',
            'statusFilter',
            'adminFilter',
            'adminList',
            'search',
            'availableCars',
            'integration'
        ));
    }

    /**
     * Admin takeover action.
     */
    public function takeover(Request $request, Conversation $conversation)
    {
        $this->handoverService->takeoverByAdmin($conversation, Auth::user(), $request->input('note'));

        return redirect()->route('admin.conversations.index', ['selected' => $conversation->id])
            ->with('success', 'Percakapan berhasil diambil alih oleh admin. Balasan bot otomatis dinonaktifkan.');
    }

    /**
     * Reactivate bot action.
     */
    public function resumeBot(Conversation $conversation)
    {
        $this->handoverService->reactivateBot($conversation, Auth::user());

        return redirect()->route('admin.conversations.index', ['selected' => $conversation->id])
            ->with('success', 'Bot AI berhasil diaktifkan kembali untuk melayani percakapan secara otomatis.');
    }

    /**
     * Send admin outbound message.
     */
    public function sendReply(Request $request, Conversation $conversation)
    {
        $request->validate([
            'message' => 'required|string',
            'media_url' => 'nullable|string',
        ]);

        $this->messageService->sendOutboundMessage(
            $conversation,
            $request->input('message'),
            'admin',
            $request->input('media_url')
        );

        // Crucial: Bot tetap aktif ketika admin membalas, sehingga pertanyaan customer berikutnya langsung dijawab bot!
        $conversation->update([
            'is_bot_active' => true,
            'is_admin_takeover' => false,
            'status' => 'BOT_ACTIVE',
        ]);

        \App\Models\HandoverLog::create([
            'conversation_id' => $conversation->id,
            'trigger_type' => 'MANUAL_ADMIN',
            'action' => 'ADMIN_REPLIED_BOT_ACTIVE',
            'reason' => 'Admin membalas dari dashboard web. Bot tetap aktif melayani pertanyaan customer selanjutnya.',
            'performed_by_user_id' => Auth::id(),
        ]);

        return redirect()->route('admin.conversations.index', ['selected' => $conversation->id])
            ->with('success', 'Pesan admin berhasil dikirim. Bot tetap aktif melayani chat berikutnya.');
    }

    /**
     * Delete conversation and related messages.
     */
    public function destroy(Conversation $conversation)
    {
        $conversationId = $conversation->id;
        $customerName = $conversation->customer->name ?? 'Customer';

        $conversation->delete();

        return redirect()->route('admin.conversations.index')
            ->with('success', "Chat percakapan #{$conversationId} ({$customerName}) berhasil dihapus.");
    }

    /**
     * Send simulated incoming customer message from dashboard for testing.
     */
    public function simulateCustomerMessage(Request $request, Conversation $conversation)
    {
        $request->validate([
            'customer_message' => 'required|string',
        ]);

        $customer = $conversation->customer;
        $simulatedWaId = 'SIM-UI-' . uniqid();

        $ingest = $this->messageService->ingestIncomingMessage(
            fromNumber: $customer->whatsapp_number,
            customerName: $customer->name,
            messageBody: $request->input('customer_message'),
            waMessageId: $simulatedWaId,
            messageType: 'text',
            metadata: ['simulated_source' => 'admin_dashboard_ui']
        );

        // Process through AI
        $botReply = $this->aiAssistant->handleIncomingMessage($ingest['conversation'], $ingest['message']);

        $message = $botReply ? 'Pesan customer berhasil disimulasikan dan dibalas oleh Bot AI!' : 'Pesan customer tersimpan (Bot tidak membalas karena status Admin Active / Handover).';

        return redirect()->route('admin.conversations.index', ['selected' => $conversation->id])
            ->with('success', $message);
    }

    /**
     * Update customer / conversation notes and interested car.
     */
    public function updateDetails(Request $request, Conversation $conversation)
    {
        $request->validate([
            'interested_car_id' => 'nullable|exists:wa_cars,id',
            'interest_level' => 'nullable|in:LOW,MEDIUM,HIGH,HOT',
            'internal_notes' => 'nullable|string',
            'lead_status' => 'nullable|in:NEW,QUALIFIED,HOT,NEGOTIATION,BOOKED,WON,LOST',
        ]);

        $conversation->update([
            'interested_car_id' => $request->input('interested_car_id'),
            'interest_level' => $request->input('interest_level', $conversation->interest_level),
            'internal_notes' => $request->input('internal_notes'),
        ]);

        if ($request->filled('lead_status')) {
            $this->leadService->syncLeadFromConversation($conversation, [
                'lead_status' => $request->input('lead_status'),
                'interest_level' => $request->input('interest_level', $conversation->interest_level),
            ]);
        }

        return redirect()->route('admin.conversations.index', ['selected' => $conversation->id])
            ->with('success', 'Detail percakapan berhasil diperbarui.');
    }
}

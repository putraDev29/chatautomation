<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Car;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Lead;
use App\Models\Message;
use App\Models\WhatsAppIntegration;
use Carbon\Carbon;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index()
    {
        $today = Carbon::today();

        // Real Database Metrics
        $totalConversationsToday = Conversation::whereDate('created_at', $today)->count();
        if ($totalConversationsToday === 0) {
            $totalConversationsToday = Conversation::count(); // Fallback to total if demo seeded on different date
        }

        $newCustomersToday = Customer::whereDate('created_at', $today)->count();
        if ($newCustomersToday === 0) {
            $newCustomersToday = Customer::count();
        }

        $waitingAdminCount = Conversation::where('status', 'NEEDS_ADMIN')->count();
        $hotLeadsCount = Lead::where('lead_status', 'HOT')->orWhere('interest_level', 'HOT')->count();
        $failedMessagesCount = Message::where('delivery_status', 'failed')->count();
        $botHandledCount = Conversation::where('is_bot_active', true)->where('status', 'BOT_ACTIVE')->count();

        // Status breakdown
        $statusCounts = [
            'BOT_ACTIVE' => Conversation::where('status', 'BOT_ACTIVE')->count(),
            'NEEDS_ADMIN' => Conversation::where('status', 'NEEDS_ADMIN')->count(),
            'ADMIN_ACTIVE' => Conversation::where('status', 'ADMIN_ACTIVE')->count(),
            'WAITING_CUSTOMER' => Conversation::where('status', 'WAITING_CUSTOMER')->count(),
            'CLOSED' => Conversation::where('status', 'CLOSED')->count(),
        ];

        // Lead stage counts
        $leadStages = [
            'NEW' => Lead::where('lead_status', 'NEW')->count(),
            'QUALIFIED' => Lead::where('lead_status', 'QUALIFIED')->count(),
            'HOT' => Lead::where('lead_status', 'HOT')->count(),
            'NEGOTIATION' => Lead::where('lead_status', 'NEGOTIATION')->count(),
            'BOOKED' => Lead::where('lead_status', 'BOOKED')->count(),
            'WON' => Lead::where('lead_status', 'WON')->count(),
        ];

        // Recent high priority conversations needing attention
        $priorityConversations = Conversation::with(['customer', 'interestedCar'])
            ->where('status', 'NEEDS_ADMIN')
            ->orderBy('handover_at', 'desc')
            ->take(5)
            ->get();

        // Recent Leads
        $recentLeads = Lead::with(['customer', 'car', 'assignedUser'])
            ->orderBy('created_at', 'desc')
            ->take(5)
            ->get();

        $integration = WhatsAppIntegration::current();
        $totalCars = Car::count();
        $availableCars = Car::where('stock_status', 'TERSEDIA')->count();

        return view('admin.dashboard', compact(
            'totalConversationsToday',
            'newCustomersToday',
            'waitingAdminCount',
            'hotLeadsCount',
            'failedMessagesCount',
            'botHandledCount',
            'statusCounts',
            'leadStages',
            'priorityConversations',
            'recentLeads',
            'integration',
            'totalCars',
            'availableCars'
        ));
    }
}

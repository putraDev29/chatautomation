<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Lead;
use App\Models\User;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');
        $query = Lead::with(['customer', 'car', 'assignedUser'])->latest();

        if (!empty($status)) {
            $query->where('lead_status', $status);
        }

        $leads = $query->paginate(15)->withQueryString();
        $users = User::all();

        return view('admin.leads.index', compact('leads', 'status', 'users'));
    }

    public function update(Request $request, Lead $lead)
    {
        $validated = $request->validate([
            'lead_status' => 'required|in:NEW,QUALIFIED,HOT,NEGOTIATION,BOOKED,WON,LOST',
            'interest_level' => 'required|in:LOW,MEDIUM,HIGH,HOT',
            'payment_method' => 'required|in:CASH,CREDIT,UNKNOWN',
            'budget' => 'nullable|numeric',
            'test_drive_at' => 'nullable|date',
            'assigned_user_id' => 'nullable|exists:users,id',
            'notes' => 'nullable|string',
        ]);

        $lead->update($validated);

        return back()->with('success', 'Data lead berhasil diperbarui.');
    }
}

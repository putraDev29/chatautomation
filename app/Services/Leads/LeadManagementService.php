<?php

namespace App\Services\Leads;

use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Lead;

class LeadManagementService
{
    /**
     * Create or update lead from conversation details.
     */
    public function syncLeadFromConversation(Conversation $conversation, array $attributes = []): Lead
    {
        $customer = $conversation->customer;

        $lead = Lead::firstOrNew(['customer_id' => $customer->id]);

        if (!empty($conversation->interested_car_id)) {
            $lead->car_id = $conversation->interested_car_id;
        }

        if (isset($attributes['budget'])) {
            $lead->budget = $attributes['budget'];
        }

        if (isset($attributes['payment_method'])) {
            $lead->payment_method = strtoupper($attributes['payment_method']);
        }

        if (isset($attributes['destination_city'])) {
            $lead->destination_city = $attributes['destination_city'];
        }

        if (isset($attributes['lead_status'])) {
            $lead->lead_status = strtoupper($attributes['lead_status']);
        } elseif ($conversation->status === 'NEEDS_ADMIN') {
            $lead->lead_status = 'HOT';
        }

        if (isset($attributes['interest_level'])) {
            $lead->interest_level = $attributes['interest_level'];
        } elseif ($conversation->interest_level) {
            $lead->interest_level = $conversation->interest_level;
        }

        if (isset($attributes['test_drive_at'])) {
            $lead->test_drive_at = $attributes['test_drive_at'];
        }

        if (isset($attributes['notes'])) {
            $lead->notes = $attributes['notes'];
        }

        $lead->save();

        return $lead;
    }
}

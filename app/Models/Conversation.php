<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    use HasFactory;

    protected $table = 'wa_conversations';

    protected $fillable = [
        'customer_id',
        'wa_conversation_id',
        'status',
        'is_bot_active',
        'is_admin_takeover',
        'assigned_admin_id',
        'wa_session_id',
        'interested_car_id',
        'interest_level',
        'last_message_at',
        'handover_at',
        'handover_reason',
        'internal_notes',
    ];

    protected $casts = [
        'is_bot_active' => 'boolean',
        'is_admin_takeover' => 'boolean',
        'last_message_at' => 'datetime',
        'handover_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function assignedAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function interestedCar(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'interested_car_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'conversation_id')->orderBy('created_at', 'asc');
    }

    public function handoverLogs(): HasMany
    {
        return $this->hasMany(HandoverLog::class, 'conversation_id')->orderBy('created_at', 'desc');
    }

    public function latestMessage()
    {
        return $this->hasOne(Message::class, 'conversation_id')->latestOfMany();
    }
}

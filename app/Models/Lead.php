<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use HasFactory;

    protected $table = 'wa_leads';

    protected $fillable = [
        'customer_id',
        'car_id',
        'budget',
        'payment_method',
        'destination_city',
        'lead_status',
        'interest_level',
        'test_drive_at',
        'assigned_user_id',
        'notes',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'test_drive_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_id');
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }
}

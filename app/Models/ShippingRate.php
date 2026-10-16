<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShippingRate extends Model
{
    use HasFactory;

    protected $table = 'wa_shipping_rates';

    protected $fillable = [
        'origin_city',
        'destination_city',
        'transport_method',
        'estimated_cost_min',
        'estimated_cost_max',
        'estimated_days_min',
        'estimated_days_max',
        'operational_notes',
        'is_active',
    ];

    protected $casts = [
        'estimated_cost_min' => 'decimal:2',
        'estimated_cost_max' => 'decimal:2',
        'estimated_days_min' => 'integer',
        'estimated_days_max' => 'integer',
        'is_active' => 'boolean',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Car extends Model
{
    use HasFactory;

    protected $table = 'wa_cars';

    protected $fillable = [
        'stock_code',
        'brand',
        'model',
        'variant',
        'year',
        'price',
        'internal_special_price',
        'transmission',
        'fuel_type',
        'color',
        'mileage',
        'tax_date',
        'stnk_status',
        'bpkb_status',
        'stock_status',
        'description',
        'location',
        'main_photo',
        'is_published',
        'is_demo',
    ];

    protected $casts = [
        'year' => 'integer',
        'price' => 'decimal:2',
        'internal_special_price' => 'decimal:2',
        'mileage' => 'integer',
        'tax_date' => 'date',
        'is_published' => 'boolean',
        'is_demo' => 'boolean',
    ];

    public function media(): HasMany
    {
        return $this->hasMany(CarMedia::class, 'car_id')->where('is_active', true)->orderBy('sort_order');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'car_id');
    }

    public function scopeAvailable($query)
    {
        return $query->where('stock_status', 'TERSEDIA')->where('is_published', true);
    }

    public function getFormattedPriceAttribute(): string
    {
        return 'Rp ' . number_format($this->price, 0, ',', '.');
    }

    public function getFormattedMileageAttribute(): string
    {
        return number_format($this->mileage, 0, ',', '.') . ' km';
    }

    public function getFullNameAttribute(): string
    {
        return "{$this->brand} {$this->model} {$this->variant} ({$this->year})";
    }
}

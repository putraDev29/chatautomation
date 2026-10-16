<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CarMedia extends Model
{
    use HasFactory;

    protected $table = 'wa_car_media';

    protected $fillable = [
        'car_id',
        'media_type',
        'file_path',
        'file_url',
        'file_size',
        'mime_type',
        'sort_order',
        'caption',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
        'file_size' => 'integer',
    ];

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class, 'car_id');
    }
}

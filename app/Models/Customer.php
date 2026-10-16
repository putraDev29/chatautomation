<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $table = 'wa_customers';

    protected $fillable = [
        'name',
        'whatsapp_number',
        'city',
        'customer_source',
        'internal_notes',
        'communication_status',
        'last_interaction_at',
    ];

    protected $casts = [
        'last_interaction_at' => 'datetime',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'customer_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class, 'customer_id');
    }

    public function latestConversation()
    {
        return $this->hasOne(Conversation::class, 'customer_id')->latestOfMany();
    }

    /**
     * Normalize WhatsApp phone number to standard international format (e.g. 628123456789)
     */
    public static function formatWhatsAppNumber(string $number): string
    {
        $clean = preg_replace('/[^0-9]/', '', $number);
        if (str_starts_with($clean, '0')) {
            $clean = '62' . substr($clean, 1);
        } elseif (str_starts_with($clean, '+62')) {
            $clean = substr($clean, 1);
        }
        return $clean;
    }
}

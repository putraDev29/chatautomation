<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WhatsAppIntegration extends Model
{
    use HasFactory;

    protected $table = 'wa_integrations';

    protected $fillable = [
        'name',
        'whatsapp_mode',
        'whatsapp_provider',
        'ai_driver',
        'is_ai_enabled',
        'ai_model',
        'ai_max_tokens',
        'business_hours_start',
        'business_hours_end',
        'handover_auto_trigger',
        'handover_keywords',
        'default_greeting',
        'daily_ai_limit',
        'current_daily_ai_usage',
        'settings',
    ];

    protected $casts = [
        'is_ai_enabled' => 'boolean',
        'handover_auto_trigger' => 'boolean',
        'ai_max_tokens' => 'integer',
        'daily_ai_limit' => 'integer',
        'current_daily_ai_usage' => 'integer',
        'settings' => 'array',
    ];

    public static function current(): self
    {
        return static::firstOrCreate(
            ['name' => 'default'],
            [
                'whatsapp_mode' => config('whatsapp.mode', 'simulation'),
                'whatsapp_provider' => config('whatsapp.provider', 'simulated'),
                'ai_driver' => config('ai.driver', 'simulation'),
                'is_ai_enabled' => true,
                'ai_model' => config('ai.model', 'gpt-4o-mini'),
                'ai_max_tokens' => (int) config('ai.max_tokens', 500),
                'business_hours_start' => '08:00:00',
                'business_hours_end' => '21:00:00',
                'handover_auto_trigger' => true,
                'handover_keywords' => 'nego,diskon,harga pas,ketemu,test drive,kredit,dp,cicilan,serius,admin,manusia,sales',
                'default_greeting' => 'Halo! Selamat datang di Bintang Mobil Surabaya. Ada unit mobil yang sedang dicari Kak? 😊',
                'daily_ai_limit' => 1000,
                'current_daily_ai_usage' => 0,
            ]
        );
    }
}

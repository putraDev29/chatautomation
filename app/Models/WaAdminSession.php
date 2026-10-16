<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WaAdminSession extends Model
{
    use HasFactory;

    protected $table = 'wa_admin_sessions';

    protected $fillable = [
        'user_id',
        'session_id',
        'session_name',
        'phone_number',
        'status',
        'qr_code',
        'device_name',
        'last_active_at',
    ];

    protected $casts = [
        'last_active_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'wa_session_id', 'session_id');
    }
}

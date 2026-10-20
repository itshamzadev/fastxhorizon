<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

class ErpApiToken extends Model
{
    protected $fillable = [
        'name',
        'api_key',
        'api_secret',
        'webhook_url',
        'is_active',
        'last_used_at',
        'webhook_last_dispatched_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_used_at' => 'datetime',
        'webhook_last_dispatched_at' => 'datetime',
    ];

    protected $hidden = [
        'api_secret',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeWithWebhook(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereNotNull('webhook_url');
    }

    /**
     * The plaintext secret recovered from encrypted storage, used to verify
     * inbound requests and to sign outbound webhook payloads. Returns null if
     * the stored value cannot be decrypted (e.g. a legacy hashed token).
     */
    public function decryptedSecret(): ?string
    {
        try {
            return Crypt::decryptString($this->api_secret);
        } catch (\Throwable) {
            return null;
        }
    }
}

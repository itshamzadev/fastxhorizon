<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerAttribution extends Model
{
    protected $fillable = ['partner_profile_id', 'user_id', 'order_id', 'program', 'code', 'landing_url', 'visitor_token_hash', 'first_at', 'last_at', 'expires_at'];
    protected $casts = ['first_at' => 'datetime', 'last_at' => 'datetime', 'expires_at' => 'datetime'];
    public function profile(): BelongsTo { return $this->belongsTo(PartnerProfile::class, 'partner_profile_id'); }
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}

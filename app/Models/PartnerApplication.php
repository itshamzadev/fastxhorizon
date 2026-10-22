<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartnerApplication extends Model
{
    protected $fillable = ['user_id', 'program', 'display_name', 'phone', 'city', 'province', 'address', 'sales_channel', 'reason', 'admin_notes', 'status', 'submitted_at', 'reviewed_at', 'reviewed_by'];
    protected $casts = ['submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(Admin::class, 'reviewed_by'); }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PartnerApplication extends Model
{
    protected $fillable = ['user_id', 'program', 'reference', 'display_name', 'phone', 'city', 'province', 'country', 'national_id', 'address', 'sales_channel', 'reason', 'business_data', 'sales_data', 'admin_notes', 'rejection_reason', 'status', 'submitted_at', 'terms_accepted_at', 'terms_version', 'reviewed_at', 'approved_at', 'rejected_at', 'reviewed_by'];
    protected $casts = ['business_data' => 'array', 'sales_data' => 'array', 'national_id' => 'encrypted', 'submitted_at' => 'datetime', 'terms_accepted_at' => 'datetime', 'reviewed_at' => 'datetime', 'approved_at' => 'datetime', 'rejected_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function reviewer(): BelongsTo { return $this->belongsTo(Admin::class, 'reviewed_by'); }
    public function documents(): HasMany { return $this->hasMany(PartnerApplicationDocument::class); }
}

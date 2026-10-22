<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerAuditEvent extends Model
{
    protected $fillable = ['admin_id', 'partner_profile_id', 'program', 'action', 'metadata'];
    protected $casts = ['metadata' => 'array'];
    public function admin() { return $this->belongsTo(Admin::class); }
    public function profile() { return $this->belongsTo(PartnerProfile::class, 'partner_profile_id'); }
}

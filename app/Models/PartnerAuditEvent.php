<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PartnerAuditEvent extends Model
{
    protected $fillable = ['admin_id', 'partner_profile_id', 'program', 'action', 'metadata'];
    protected $casts = ['metadata' => 'array'];
}

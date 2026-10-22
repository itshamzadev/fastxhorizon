<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SellerSettingAudit extends Model
{
    protected $fillable = ['seller_id', 'admin_id', 'setting', 'old_value', 'new_value'];

    protected $casts = [
        'old_value' => 'boolean',
        'new_value' => 'boolean',
    ];
}

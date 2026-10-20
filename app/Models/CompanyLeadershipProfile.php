<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyLeadershipProfile extends Model
{
    protected $fillable = ['name', 'role', 'photo', 'bio', 'portfolio_url', 'display_order', 'is_active'];
    protected $casts = ['is_active' => 'boolean', 'display_order' => 'integer'];
}

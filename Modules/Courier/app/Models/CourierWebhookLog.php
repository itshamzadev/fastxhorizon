<?php

namespace Modules\Courier\app\Models;

use Illuminate\Database\Eloquent\Model;

class CourierWebhookLog extends Model
{
    protected $fillable = [
        'provider',
        'consignment_id',
        'verified',
        'headers',
        'payload',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'headers'  => 'array',
        'payload'  => 'array',
    ];
}

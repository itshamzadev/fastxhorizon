<?php

namespace Modules\Courier\app\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Courier\app\Enums\ShipmentStatus;

class CourierTrackingEvent extends Model
{
    protected $fillable = [
        'courier_shipment_id',
        'status',
        'location',
        'description',
        'occurred_at',
        'payload',
    ];

    protected $casts = [
        'status'      => ShipmentStatus::class,
        'occurred_at' => 'datetime',
        'payload'     => 'array',
    ];

    public function shipment(): BelongsTo
    {
        return $this->belongsTo(CourierShipment::class, 'courier_shipment_id');
    }
}

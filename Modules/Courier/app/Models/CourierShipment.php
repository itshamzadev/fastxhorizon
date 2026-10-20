<?php

namespace Modules\Courier\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Courier\app\Enums\ShipmentStatus;
use Modules\Courier\app\ValueObjects\CourierOwner;

class CourierShipment extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_id',
        'provider',
        'consignment_id',
        'host_order_reference',
        'tracking_code',
        'status',
        'delivery_fee',
        'cod_amount',
        'payload',
        'dispatch_details',
        'superseded_at',
    ];

    protected $casts = [
        'status'           => ShipmentStatus::class,
        'delivery_fee'     => 'decimal:2',
        'cod_amount'       => 'decimal:2',
        'payload'          => 'array',
        'dispatch_details' => 'array',
        'superseded_at'    => 'datetime',
    ];

    public function trackingEvents(): HasMany
    {
        return $this->hasMany(CourierTrackingEvent::class);
    }

    public function scopeForOwner(Builder $query, CourierOwner $owner): Builder
    {
        return $query->where('owner_type', $owner->type)->where('owner_id', $owner->id);
    }

    public function owner(): CourierOwner
    {
        return CourierOwner::of(type: $this->owner_type, id: $this->owner_id);
    }

    public function scopeForConsignment(Builder $query, string $provider, string $consignmentId): Builder
    {
        return $query->where('provider', $provider)->where('consignment_id', $consignmentId);
    }

    public function scopeForHostReference(Builder $query, string $hostOrderReference): Builder
    {
        return $query->where('host_order_reference', $hostOrderReference);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('superseded_at');
    }

    public function supersede(): void
    {
        $this->forceFill(['superseded_at' => now()])->save();
    }
}

<?php

namespace Modules\Courier\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Courier\app\ValueObjects\CourierOwner;

class CourierProviderSetting extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_id',
        'provider',
        'is_active',
        'credentials',
        'settings',
    ];

    protected $casts = [
        'is_active'   => 'boolean',
        'credentials' => 'encrypted:array',
        'settings'    => 'array',
    ];

    public function scopeForOwner(Builder $query, CourierOwner $owner): Builder
    {
        return $query->where('owner_type', $owner->type)->where('owner_id', $owner->id);
    }

    public function owner(): CourierOwner
    {
        return CourierOwner::of(type: $this->owner_type, id: $this->owner_id);
    }
}

<?php

namespace Modules\Courier\app\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Modules\Courier\app\ValueObjects\CourierOwner;

class CourierProviderAddress extends Model
{
    protected $fillable = [
        'owner_type',
        'owner_id',
        'provider',
        'environment',
        'address_hash',
        'remote_address_id',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];

    public function scopeForAddress(
        Builder $query,
        CourierOwner $owner,
        string $provider,
        string $environment,
        string $addressHash,
    ): Builder {
        return $query->where('owner_type', $owner->type)
            ->where('owner_id', $owner->id)
            ->where('provider', $provider)
            ->where('environment', $environment)
            ->where('address_hash', $addressHash);
    }
}

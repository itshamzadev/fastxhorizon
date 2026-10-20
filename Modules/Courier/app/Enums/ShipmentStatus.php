<?php

namespace Modules\Courier\app\Enums;

enum ShipmentStatus: string
{
    case Pending = 'pending';
    case PickupRequested = 'pickup_requested';
    case PickedUp = 'picked_up';
    case InTransit = 'in_transit';
    case AtHub = 'at_hub';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case PartialDelivered = 'partial_delivered';
    case ReturnInitiated = 'return_initiated';
    case Returned = 'returned';
    case Cancelled = 'cancelled';
    case OnHold = 'on_hold';
    case Failed = 'failed';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::PartialDelivered => 'Partially Delivered',
            self::Unknown          => 'Not Reported Yet',
            default                => ucwords(str_replace('_', ' ', $this->value)),
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Delivered                                          => 'success',
            self::Cancelled, self::Failed, self::Returned            => 'danger',
            self::OnHold, self::ReturnInitiated, self::PartialDelivered => 'warning',
            self::Unknown, self::Pending                             => 'neutral',
            default                                                  => 'info',
        };
    }
}

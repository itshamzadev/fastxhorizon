<?php

namespace Modules\Courier\app\Http\Concerns;

use Illuminate\Support\Carbon;
use Modules\Courier\app\DataTransferObjects\Responses\TrackingEvent;
use Modules\Courier\app\Enums\ShipmentStatus;

trait PresentsTrackingEvents
{
    private const TRACKING_TIME_FORMAT = 'd M Y, h:i A';

    protected function presentTrackingEvents(array $events): array
    {
        return array_map(
            fn (TrackingEvent $event): array => [
                'status'      => $event->status->value,
                'label'       => $this->trackingEventLabel($event),
                'tone'        => $event->status->tone(),
                'time'        => $this->trackingEventTime($event->occurredAt),
                'description' => $event->description,
            ],
            $events,
        );
    }

    protected function presentShipmentStatus(?ShipmentStatus $status): ?array
    {
        if ($status === null) {
            return null;
        }

        return [
            'status' => $status->value,
            'label'  => $this->statusLabel($status),
            'tone'   => $status->tone(),
        ];
    }

    private function trackingEventLabel(TrackingEvent $event): string
    {
        if ($event->status === ShipmentStatus::Unknown && filled($event->description)) {
            return $event->description;
        }

        return $this->statusLabel($event->status);
    }

    private function statusLabel(ShipmentStatus $status): string
    {
        return translate($status->label());
    }

    private function trackingEventTime(string $occurredAt): string
    {
        if (blank($occurredAt)) {
            return '';
        }

        return rescue(
            fn (): string => Carbon::parse($occurredAt)
                ->setTimezone(date_default_timezone_get())
                ->format(self::TRACKING_TIME_FORMAT),
            $occurredAt,
            report: false,
        );
    }
}

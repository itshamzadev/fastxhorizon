<?php

namespace Modules\Courier\app\ValueObjects;

final class CourierOwner
{
    public const TYPE_PLATFORM = 'platform';

    public const TYPE_VENDOR = 'vendor';

    private function __construct(
        public readonly string $type,
        public readonly int $id,
    ) {}

    public static function platform(): self
    {
        return new self(type: self::TYPE_PLATFORM, id: 0);
    }

    public static function vendor(int $id): self
    {
        return new self(type: self::TYPE_VENDOR, id: $id);
    }

    public static function of(string $type, int|string|null $id): self
    {
        return $type === self::TYPE_PLATFORM || $type === ''
            ? self::platform()
            : new self(type: $type, id: (int) $id);
    }

    public static function fromRouteKey(?string $key): self
    {
        return $key === null || !str_contains($key, '-')
            ? self::platform()
            : self::of(...array_combine(['type', 'id'], explode('-', $key, 2)));
    }

    public function routeKey(): string
    {
        return $this->type.'-'.$this->id;
    }

    public function isPlatform(): bool
    {
        return $this->type === self::TYPE_PLATFORM;
    }

    public function equals(self $other): bool
    {
        return $this->type === $other->type && $this->id === $other->id;
    }
}

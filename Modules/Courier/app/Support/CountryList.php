<?php

namespace Modules\Courier\app\Support;

class CountryList
{
    private static ?array $countries = null;

    public static function all(): array
    {
        return self::$countries ??= require dirname(__DIR__, 2).'/resources/data/countries.php';
    }

    public static function options(): array
    {
        return array_map(
            static fn (string $code, string $name): array => ['id' => $code, 'name' => $name],
            array_keys(self::all()),
            array_values(self::all()),
        );
    }

    public static function nameFor(?string $code): ?string
    {
        return $code === null ? null : (self::all()[strtoupper($code)] ?? null);
    }
}

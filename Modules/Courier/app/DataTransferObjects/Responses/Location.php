<?php

namespace Modules\Courier\app\DataTransferObjects\Responses;

class Location
{
    public readonly string $name;

    public function __construct(
        public readonly string  $id,
        string                  $name,
        public readonly string  $level,
        public readonly ?string $parentId = null,
        public readonly array   $raw = [],
    ) {
        $this->name = self::normalizeName($name);
    }

    // Carrier catalogues are merchant-typed free text: names arrive with newlines, tabs and
    // doubled spaces that break the option list. Only C0/C1 controls are stripped — \p{Cf}
    // carries ZWNJ/ZWJ, which are meaningful in Bengali, Hindi and Arabic names.
    public static function normalizeName(string $name): string
    {
        $printable = (string) preg_replace('/\p{Cc}+/u', ' ', $name);

        return trim((string) preg_replace('/\s+/u', ' ', $printable));
    }
}

<?php

namespace App\Traits;

trait NormalizesPhoneInput
{
    protected function preparePhoneInput(string $field = 'phone'): void
    {
        if ($this->filled($field)) {
            $this->merge([
                $field => $this->normalizePhoneInput((string) $this->input($field)),
            ]);
        }
    }

    protected function normalizePhoneInput(string $phone): string
    {
        $phone = trim($phone);
        if ($phone === '') {
            return '';
        }

        $clean = preg_replace('/[^0-9+]/', '', $phone) ?: '';
        $digits = preg_replace('/[^0-9]/', '', $clean) ?: '';
        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (preg_match('/^0?3\d{9}$/', $digits)) {
            return '+92' . ltrim($digits, '0');
        }
        if (str_starts_with($digits, '92') && strlen($digits) >= 12) {
            return '+' . $digits;
        }

        if (str_starts_with($clean, '+')) {
            return '+' . $digits;
        }

        // An unprefixed non-Pakistani number has no country context here.
        // Preserve it rather than guessing a country code.
        return $phone;
    }
}

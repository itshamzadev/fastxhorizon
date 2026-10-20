<?php

namespace Modules\AI\app\Tools\Concerns;

trait MatchesVariantOption
{
    /** @param array<int, string> $optionValues */
    protected function matchOptionValue(array $optionValues, string $value): ?string
    {
        $value = trim($value);
        if ($value === '' || empty($optionValues)) {
            return null;
        }

        foreach ($optionValues as $option) {
            if (strcasecmp((string) $option, $value) === 0) {
                return (string) $option;
            }
        }

        $normValue = $this->normalizeOptionToken($value);
        if ($normValue !== '') {
            $matches = $this->uniqueMatch(
                $optionValues,
                fn($o) => $this->normalizeOptionToken((string) $o) === $normValue,
            );
            if ($matches !== null) {
                return $matches;
            }
        }

        $valueDigits = $this->digitsOf($value);
        if ($valueDigits !== '') {
            $matches = $this->uniqueMatch(
                $optionValues,
                fn($o) => $this->digitsOf((string) $o) === $valueDigits,
            );
            if ($matches !== null) {
                return $matches;
            }
        }

        return null;
    }

    private function uniqueMatch(array $optionValues, callable $predicate): ?string
    {
        $matches = array_values(array_filter($optionValues, $predicate));

        return count($matches) === 1 ? (string) $matches[0] : null;
    }

    private function normalizeOptionToken(string $value): string
    {
        return strtolower((string) preg_replace('/[^a-z0-9]/i', '', $value));
    }

    private function digitsOf(string $value): string
    {
        return (string) preg_replace('/\D+/', '', $value);
    }
}

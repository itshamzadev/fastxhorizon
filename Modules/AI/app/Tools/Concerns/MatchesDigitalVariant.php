<?php

namespace Modules\AI\app\Tools\Concerns;

/** Resolve a customer-named digital edition to its stored `variant_key` (kept in the cart's `variant` column, not the color/choice composite physical products use). */
trait MatchesDigitalVariant
{
    protected function matchDigitalVariant(array $variants, string $value): ?string
    {
        $normalize = fn($text) => strtolower(preg_replace('/[\s_\-]+/', '', (string) $text));
        $target    = $normalize($value);
        if ($target === '') {
            return null;
        }

        foreach ($variants as $variant) {
            if ($normalize($variant['variant_key'] ?? '') === $target || $normalize($variant['label'] ?? '') === $target) {
                return $variant['variant_key'];
            }
        }
        foreach ($variants as $variant) {
            if (str_contains($normalize($variant['label'] ?? ''), $target) || str_contains($normalize($variant['variant_key'] ?? ''), $target)) {
                return $variant['variant_key'];
            }
        }

        return null;
    }

    protected function digitalVariantCandidates(?string $color, mixed $options): array
    {
        $candidates = [];

        $color = trim((string) $color);
        if ($color !== '') {
            $candidates[] = $color;
        }

        foreach ((array) $options as $key => $value) {
            if (is_string($key) && trim($key) !== '') {
                $candidates[] = trim($key);
            }
            $value = trim((string) $value);
            if ($value !== '') {
                $candidates[] = $value;
            }
        }

        return array_values(array_unique($candidates));
    }

    protected function resolveDigitalVariantKey(array $variants, ?string $color, mixed $options): ?string
    {
        foreach ($this->digitalVariantCandidates($color, $options) as $candidate) {
            if ($key = $this->matchDigitalVariant($variants, $candidate)) {
                return $key;
            }
        }

        return null;
    }
}

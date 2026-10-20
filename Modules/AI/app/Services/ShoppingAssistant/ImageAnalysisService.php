<?php

namespace Modules\AI\app\Services\ShoppingAssistant;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\AI\AIProviders\AIProviderManager;

class ImageAnalysisService
{
    private const PROMPT = 'Analyze this product image. Return ONLY a valid JSON object — no extra text — with exactly these keys: "keywords" (array of 3-6 search terms: product type, color, material, style, brand if visible), "category" (most likely product category string), "attributes" (object of specific key-value pairs such as color, material, style, gender). Example: {"keywords":["blue cotton t-shirt","casual wear","men fashion"],"category":"clothing","attributes":{"color":"blue","material":"cotton","style":"casual","gender":"men"}}.';
    public function __construct(private readonly AIProviderManager $providerManager) {}

    public function extractProductKeywords(string $imageUrl): array
    {
        $cacheKey = 'ai_img:' . md5($imageUrl);

        $cached = Cache::get($cacheKey);
        if (is_array($cached)) {
            return $cached;
        }

        $analysis = $this->analyze($imageUrl);

        // Cache only a usable result: caching an empty one would poison the constant dev image URL for 24h.
        if (!$this->isEmpty($analysis)) {
            Cache::put($cacheKey, $analysis, now()->addHours(24));
        }

        return $analysis;
    }

    private function analyze(string $imageUrl): array
    {
        try {
            $provider = $this->providerManager->getAvailableProviderObject();
            $raw      = $provider->generate(prompt: self::PROMPT, imageUrl: $imageUrl, options: ['max_tokens' => 256]);
        } catch (\Throwable $e) {
            Log::warning('AI image analysis: provider call failed', ['error' => $e->getMessage()]);
            return $this->emptyAnalysis();
        }

        $json = $this->decodeModelJson($raw);
        if (!is_array($json)) {
            Log::warning('AI image analysis: no parseable JSON in response', ['raw' => mb_substr((string) $raw, 0, 300)]);
            return $this->emptyAnalysis();
        }

        // Untrusted model output is concatenated into the agent prompt; reduce to short tokens to blunt prompt-injection via a crafted image.
        return [
            'keywords'   => array_slice(array_map([$this, 'cleanToken'], (array) ($json['keywords'] ?? [])), 0, 6),
            'category'   => $this->cleanToken((string) ($json['category'] ?? '')),
            'attributes' => $this->cleanAttributes((array) ($json['attributes'] ?? [])),
        ];
    }

    private function decodeModelJson(string $raw): mixed
    {
        $text = trim($raw);

        if (str_starts_with($text, '```')) {
            $text = trim(preg_replace('/^```[a-zA-Z]*\s*|\s*```$/', '', $text));
        }

        $decoded = json_decode($text, associative: true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $text, $match)) {
            return json_decode($match[0], associative: true);
        }

        return null;
    }

    private function emptyAnalysis(): array
    {
        return ['keywords' => [], 'category' => '', 'attributes' => []];
    }

    private function isEmpty(array $analysis): bool
    {
        return empty($analysis['keywords']) && empty($analysis['category']) && empty($analysis['attributes']);
    }

    private function cleanToken(mixed $value): string
    {
        $value = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', (string) $value);
        $value = trim(preg_replace('/\s{2,}/', ' ', $value));

        return mb_substr($value, 0, 50);
    }

    private function cleanAttributes(array $attributes): array
    {
        $clean = [];
        foreach ($attributes as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }
            $cleanKey = $this->cleanToken($key);
            if ($cleanKey !== '') {
                $clean[$cleanKey] = $this->cleanToken($value);
            }
            if (count($clean) >= 6) {
                break;
            }
        }

        return $clean;
    }
}

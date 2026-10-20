<?php

namespace Modules\AI\AIProviders;

use Illuminate\Support\Facades\Http;
use Modules\AI\app\Contracts\AIProviderInterface;

class ClaudeProvider implements AIProviderInterface
{
    private const API_URL     = 'https://api.anthropic.com/v1/messages';
    private const API_VERSION = '2023-06-01';
    private const MODEL       = 'claude-sonnet-4-6';

    protected string $apiKey;
    protected ?string $organization = null;

    public function getName(): string
    {
        return 'Claude';
    }

    public function setApiKey(string $apiKey): void
    {
        $this->apiKey = $apiKey;
    }

    public function setOrganization(?string $organization): void
    {
        $this->organization = $organization;
    }

    public function generate(string $prompt, ?string $imageUrl = null, array $options = []): string
    {
        $content = [['type' => 'text', 'text' => $prompt]];

        if (!empty($imageUrl)) {
            $content[] = [
                'type'   => 'image',
                'source' => ['type' => 'url', 'url' => $imageUrl],
            ];
        }

        $payload = [
            'model'      => $options['model'] ?? self::MODEL,
            'max_tokens' => $options['max_tokens'] ?? 1024,
            'messages'   => [['role' => 'user', 'content' => $content]],
        ];

        $response = Http::withHeaders([
            'x-api-key'         => $this->apiKey,
            'anthropic-version' => self::API_VERSION,
        ])->post(self::API_URL, $payload);

        $textBlock = collect($response->json('content') ?? [])->firstWhere('type', 'text');

        return $textBlock['text'] ?? '';
    }
}

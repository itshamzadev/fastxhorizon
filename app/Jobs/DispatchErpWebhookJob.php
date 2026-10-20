<?php

namespace App\Jobs;

use App\Models\ErpApiToken;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class DispatchErpWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;


    public int $tries = 5;

    public int $maxExceptions = 5;

    public int $timeout = 120;

    public function __construct(
        protected string $event,
        protected array $payload,
    ) {}

    public function retryUntil(): \DateTime
    {
        return now()->addHours(6);
    }

    /** Growing backoff (seconds) between retries to ride out longer outages. */
    public function backoff(): array
    {
        return [60, 300, 900, 1800, 3600];
    }

    public function handle(): void
    {
        $rawBody = json_encode(
            ['event' => $this->event, 'data' => $this->payload],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $transientFailures = [];

        foreach (ErpApiToken::withWebhook()->get() as $token) {
            $secret = $token->decryptedSecret();
            if (!$secret) {
                Log::warning('ERP webhook skipped: secret not recoverable', [
                    'token_id' => $token->id,
                    'event' => $this->event,
                ]);
                continue;
            }

            try {
                $hmacKey = hash('sha256', $secret);

                $response = Http::connectTimeout(5)
                    ->timeout(15)
                    ->withHeaders([
                        'X-Webhook-Key' => $token->api_key,
                        'X-Webhook-Signature' => 'sha256=' . hash_hmac('sha256', $rawBody, $hmacKey),
                        'X-Webhook-Event' => $this->event,
                    ])
                    ->withBody($rawBody, 'application/json')
                    ->post($token->webhook_url);

                if ($response->clientError()) {
                    Log::warning('ERP webhook rejected by subscriber', [
                        'token_id' => $token->id,
                        'event' => $this->event,
                        'status' => $response->status(),
                    ]);
                    continue;
                }

                $response->throw();
                $token->forceFill(['webhook_last_dispatched_at' => now()])->saveQuietly();
            } catch (Throwable $exception) {
                // Connection error / timeout / 5xx: retryable for this subscriber.
                $transientFailures[] = $token->id;
                Log::warning('ERP webhook transient failure', [
                    'token_id' => $token->id,
                    'event' => $this->event,
                    'message' => $exception->getMessage(),
                ]);
            }
        }
        if (!empty($transientFailures)) {
            throw new RuntimeException(
                'ERP webhook had ' . count($transientFailures) . ' transient failure(s): [' . implode(',', $transientFailures) . ']'
            );
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('ERP webhook permanently failed after retries', [
            'event' => $this->event,
            'message' => $exception->getMessage(),
        ]);
    }
}

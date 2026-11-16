<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Services\PartnerWebhooks;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Envoie un webhook au partenaire. Réessaie avec un délai croissant tant que la réponse n'est pas 2xx.
 */
class SendPartnerWebhook implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public function __construct(public WebhookDelivery $delivery) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 120, 600, 1800, 3600];
    }

    public function handle(): void
    {
        $partner = $this->delivery->partner;

        if (! $partner?->is_active || ! $partner->hasWebhook()) {
            $this->delivery->update(['status' => WebhookDelivery::FAILED, 'last_error' => 'Partenaire inactif ou webhook non configuré.']);

            return;
        }

        $body = json_encode($this->delivery->payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = now()->getTimestamp();
        $this->delivery->increment('attempts');

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    PartnerWebhooks::SIGNATURE_HEADER => PartnerWebhooks::signature($partner->webhook_secret, $body, $timestamp),
                    'X-PassParking-Event' => $this->delivery->type,
                    'X-PassParking-Delivery' => $this->delivery->uuid,
                    'User-Agent' => 'PassParking-Webhooks/1.0',
                ])
                ->withBody($body, 'application/json')
                ->post($partner->webhook_url);
        } catch (Throwable $exception) {
            $this->delivery->update(['last_error' => mb_substr($exception->getMessage(), 0, 500)]);

            throw $exception;
        }

        $this->delivery->update(['response_status' => $response->status()]);

        if (! $response->successful()) {
            $this->delivery->update(['last_error' => "Réponse HTTP {$response->status()}"]);

            throw new RuntimeException("Webhook {$this->delivery->uuid} refusé : HTTP {$response->status()}");
        }

        $this->delivery->update(['status' => WebhookDelivery::DELIVERED, 'delivered_at' => now(), 'last_error' => null]);
    }

    public function failed(?Throwable $exception): void
    {
        $this->delivery->update(['status' => WebhookDelivery::FAILED]);
    }
}

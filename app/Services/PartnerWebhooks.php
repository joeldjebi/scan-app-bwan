<?php

namespace App\Services;

use App\Jobs\SendPartnerWebhook;
use App\Models\Event;
use App\Models\Partner;
use App\Models\Pass;
use App\Models\WebhookDelivery;
use Illuminate\Support\Str;

/**
 * Notifie les partenaires d'un événement (webhooks signés HMAC-SHA256, envoyés en file d'attente).
 */
class PartnerWebhooks
{
    public const PASS_REVOKED = 'pass.revoked';

    public const PASS_RESTORED = 'pass.restored';

    public const PING = 'ping';

    public const SIGNATURE_HEADER = 'X-PassParking-Signature';

    /**
     * Pass révoqué ou réactivé : les partenaires qui gardent une liste locale la mettent à jour.
     */
    public function passStatusChanged(Pass $pass, string $type): void
    {
        $pass->loadMissing('event', 'type', 'vehicle');

        $this->notifyEvent($pass->event, $type, [
            'event' => ['id' => $pass->event->id, 'name' => $pass->event->name],
            'pass' => [
                'number' => $pass->number,
                'status' => $pass->status->value,
                'type' => $pass->type->name,
                'plate' => $pass->vehicle?->plate,
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function notifyEvent(Event $event, string $type, array $data): void
    {
        $event->partners()
            ->where('is_active', true)
            ->whereNotNull('webhook_url')
            ->get()
            ->each(fn (Partner $partner) => $this->send($partner, $type, $data));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function send(Partner $partner, string $type, array $data): ?WebhookDelivery
    {
        if (! $partner->hasWebhook()) {
            return null;
        }

        $uuid = (string) Str::uuid();
        $delivery = $partner->webhookDeliveries()->create([
            'uuid' => $uuid,
            'type' => $type,
            'payload' => ['id' => $uuid, 'type' => $type, 'created_at' => now()->toIso8601String(), 'data' => $data],
            'status' => WebhookDelivery::PENDING,
        ]);

        SendPartnerWebhook::dispatch($delivery)->afterCommit();

        return $delivery;
    }

    /**
     * Signature à vérifier par le partenaire : `t=<timestamp>,v1=<hmac_sha256(secret, "<timestamp>.<corps brut>")>`.
     */
    public static function signature(string $secret, string $body, int $timestamp): string
    {
        return "t={$timestamp},v1=".hash_hmac('sha256', "{$timestamp}.{$body}", $secret);
    }
}

<?php

namespace App\Services;

use App\Enums\Direction;
use App\Enums\PassStatus;
use App\Enums\ScanMethod;
use App\Enums\ScanResult;
use App\Models\Pass;
use App\Models\Scan;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PassScanner
{
    /**
     * Retrouve un pass à partir du contenu brut du QR code (URL complète ou jeton seul).
     */
    public function find(string $code): ?Pass
    {
        $code = trim($code);

        if (preg_match('#/p/([A-Za-z0-9]+)/?(?:[?\#].*)?$#', $code, $matches)) {
            $code = $matches[1];
        }

        if (! preg_match('/^[A-Za-z0-9]{6,32}$/', $code)) {
            return null;
        }

        return Pass::with(['event', 'type', 'vehicle'])->where('token', $code)->first();
    }

    /**
     * Pass dont le véhicule porte cette immatriculation, saisie dans n'importe quel format
     * (« 1234 ab-01 » = « 1234AB01 »), limités aux événements de l'utilisateur.
     *
     * @return Collection<int, Pass>
     */
    public function findByPlate(User $user, string $plate): Collection
    {
        $key = Vehicle::plateKey($plate);

        if (strlen($key) < 2) {
            return collect();
        }

        return Pass::with(['event', 'type', 'vehicle'])
            ->whereHas('vehicle', fn ($query) => $query->where('plate_key', $key))
            ->get()
            ->filter(fn (Pass $pass) => $user->canOperate($pass->event))
            ->values();
    }

    /**
     * Identifie le pass à partir du QR code ou, à défaut, de l'immatriculation saisie
     * (y compris une immatriculation envoyée par erreur dans `code`).
     *
     * @return array{outcome: ScanOutcome, method: ScanMethod, matches: Collection<int, Pass>}
     */
    public function resolve(User $user, ?string $code, ?string $plate): array
    {
        if ($code !== null && $code !== '') {
            $pass = $this->find($code);

            // Tolérance : une immatriculation envoyée par erreur dans `code` est traitée comme
            // une saisie manuelle si elle correspond à un véhicule des événements de l'agent.
            if ($pass === null && $this->findByPlate($user, $code)->isNotEmpty()) {
                return $this->resolve($user, null, $code);
            }

            return ['outcome' => $this->check($user, $pass), 'method' => ScanMethod::Qr, 'matches' => collect()];
        }

        $matches = $this->findByPlate($user, (string) $plate);

        $outcome = match ($matches->count()) {
            0 => new ScanOutcome(null, ScanOutcome::UNKNOWN_PLATE),
            1 => $this->check($user, $matches->first()),
            default => new ScanOutcome(null, ScanOutcome::MULTIPLE_MATCHES),
        };

        return ['outcome' => $outcome, 'method' => ScanMethod::Plate, 'matches' => $matches->count() > 1 ? $matches : collect()];
    }

    /**
     * Vérifie un pass pour un agent : l'événement du pass est déterminé par le pass
     * lui-même, l'agent doit y être affecté (chef ou agent) ou être admin.
     */
    public function check(User $user, ?Pass $pass): ScanOutcome
    {
        $reason = match (true) {
            $pass === null => ScanOutcome::UNKNOWN_PASS,
            ! $user->canOperate($pass->event) => ScanOutcome::NOT_ASSIGNED,
            $pass->event->isClosed() => ScanOutcome::EVENT_CLOSED,
            $pass->status === PassStatus::Revoked => ScanOutcome::REVOKED,
            $pass->status === PassStatus::Pending => ScanOutcome::NOT_REGISTERED,
            default => null,
        };

        // Les données d'un pass ne sont jamais exposées à un agent non affecté à son événement.
        return new ScanOutcome($reason === ScanOutcome::NOT_ASSIGNED ? null : $pass, $reason);
    }

    public function canForce(User $user, ScanOutcome $outcome): bool
    {
        return $outcome->pass !== null
            && $outcome->isForceable()
            && $user->canSupervise($outcome->pass->event);
    }

    /**
     * Enregistre un passage et met à jour la position du véhicule s'il est autorisé.
     * $pass est null pour un QR code inconnu ou hors des événements de l'agent.
     *
     * @param  array{direction?: ?Direction, method?: ScanMethod, reason?: ?string, forced?: bool, offline?: bool, device_id?: ?string, latitude?: ?float, longitude?: ?float, location_accuracy?: ?int, client_uuid?: ?string, scanned_at?: ?Carbon}  $attributes
     */
    public function record(User $agent, ?Pass $pass, ScanResult $result, array $attributes = []): Scan
    {
        return DB::transaction(function () use ($agent, $pass, $result, $attributes) {
            if ($pass) {
                $pass = Pass::whereKey($pass->id)->lockForUpdate()->first();
            }

            $scannedAt = $attributes['scanned_at'] ?? now();
            $direction = $attributes['direction'] ?? $pass?->nextDirection() ?? Direction::In;

            $scan = Scan::create([
                'event_id' => $pass?->event_id,
                'pass_id' => $pass?->id,
                'user_id' => $agent->id,
                'direction' => $direction,
                'result' => $result,
                'method' => $attributes['method'] ?? ScanMethod::Qr,
                'reason' => $attributes['reason'] ?? null,
                'forced' => $attributes['forced'] ?? false,
                'offline' => $attributes['offline'] ?? false,
                'device_id' => $attributes['device_id'] ?? null,
                'latitude' => $attributes['latitude'] ?? null,
                'longitude' => $attributes['longitude'] ?? null,
                'location_accuracy' => isset($attributes['location_accuracy']) ? (int) round($attributes['location_accuracy']) : null,
                'client_uuid' => $attributes['client_uuid'] ?? null,
                'scanned_at' => $scannedAt,
            ]);

            // Un scan hors ligne plus ancien que le dernier connu ne doit pas écraser la position actuelle.
            $isLatest = ! $pass?->last_scanned_at || $scannedAt->gte($pass->last_scanned_at);

            if ($pass && $result === ScanResult::Granted && $isLatest) {
                $pass->update(['presence' => $direction, 'last_scanned_at' => $scannedAt]);
            }

            return $scan;
        });
    }
}

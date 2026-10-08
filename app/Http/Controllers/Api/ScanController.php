<?php

namespace App\Http\Controllers\Api;

use App\Enums\Direction;
use App\Enums\ScanResult;
use App\Http\Controllers\Controller;
use App\Http\Resources\PassResource;
use App\Http\Resources\ScanResource;
use App\Models\Pass;
use App\Models\Scan;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\PassScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Parcours de l'agent : scanner, vérifier, valider, consulter son historique.
 * L'événement est déduit du pass scanné ; l'agent doit y être affecté.
 */
class ScanController extends Controller
{
    public function __construct(private PassScanner $scanner) {}

    /**
     * Étape 1 : l'agent scanne le QR code, l'app affiche le véhicule à contrôler.
     * N'enregistre rien.
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate(self::identificationRules());

        ['outcome' => $outcome, 'method' => $method, 'matches' => $matches] = $this->scanner->resolve($request->user(), $request->code, $request->plate);

        return response()->json([
            'valid' => $outcome->isValid(),
            'reason' => $outcome->reason,
            'message' => $outcome->message(),
            'method' => $method,
            'can_force' => $this->scanner->canForce($request->user(), $outcome),
            'event' => $outcome->pass ? $outcome->pass->event->only(['id', 'name']) : null,
            'pass' => $outcome->pass ? new PassResource($outcome->pass) : null,
            'matches' => $this->matches($matches),
        ]);
    }

    /**
     * Étape 2 : l'agent valide l'entrée ou la sortie (sens déduit automatiquement).
     * `result = denied` trace un refus ; `force` permet au chef d'autoriser un pass non valide.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            ...self::identificationRules(),
            'result' => ['nullable', Rule::enum(ScanResult::class)],
            'direction' => ['nullable', Rule::enum(Direction::class)],
            'reason' => ['nullable', 'string', 'max:255'],
            'force' => ['boolean'],
            'client_uuid' => ['nullable', 'uuid'],
            'device_id' => ['nullable', 'string', 'max:100'],
            ...self::locationRules(),
        ]);

        if ($existing = $this->existing($data['client_uuid'] ?? null)) {
            return $this->scanResponse($existing, 200);
        }

        $user = $request->user();
        ['outcome' => $outcome, 'method' => $method, 'matches' => $matches] = $this->scanner->resolve($user, $data['code'] ?? null, $data['plate'] ?? null);
        $result = ScanResult::from($data['result'] ?? ScanResult::Granted->value);

        // Même validation renvoyée (réseau instable, double appui) : on renvoie le passage existant.
        if ($duplicate = $this->recentDuplicate($user, $outcome->pass, $result)) {
            return $this->scanResponse($duplicate, 200);
        }

        // Plaque présente sur plusieurs événements : l'app doit d'abord faire choisir le pass.
        if ($matches->isNotEmpty()) {
            return response()->json([
                'message' => $outcome->message(),
                'reason' => $outcome->reason,
                'can_force' => false,
                'matches' => $this->matches($matches),
            ], 422);
        }
        $forced = false;

        if ($result === ScanResult::Granted && ! $outcome->isValid()) {
            $canForce = $this->scanner->canForce($user, $outcome);

            if (! ($canForce && ($data['force'] ?? false))) {
                return response()->json([
                    'message' => $outcome->message(),
                    'reason' => $outcome->reason,
                    'can_force' => $canForce,
                    'matches' => [],
                ], 422);
            }

            $forced = true;
        }

        $scan = $this->scanner->record($user, $outcome->pass, $result, [
            'direction' => isset($data['direction']) ? Direction::from($data['direction']) : null,
            'method' => $method,
            'reason' => $data['reason'] ?? $outcome->reason,
            'forced' => $forced,
            'device_id' => $data['device_id'] ?? null,
            'client_uuid' => $data['client_uuid'] ?? null,
            ...self::location($data),
        ]);

        if ($forced) {
            app(AuditLogger::class)->record('scan.forced', "Passage forcé du pass « {$outcome->pass->number} » ({$outcome->message()})", $outcome->pass, [
                'motif_refus' => $outcome->reason,
                'motif_saisi' => $data['reason'] ?? null,
                'sens' => $scan->direction->value,
            ]);
        }

        return $this->scanResponse($scan, 201);
    }

    /**
     * Envoi des passages effectués hors ligne. Idempotent grâce à `client_uuid`.
     */
    public function batch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scans' => ['required', 'array', 'max:500'],
            'scans.*.client_uuid' => ['nullable', 'uuid'],
            'scans.*.code' => ['required_without:scans.*.plate', 'nullable', 'string', 'max:500'],
            'scans.*.plate' => ['required_without:scans.*.code', 'nullable', 'string', 'max:20'],
            'scans.*.result' => ['required', Rule::enum(ScanResult::class)],
            'scans.*.direction' => ['required', Rule::enum(Direction::class)],
            'scans.*.scanned_at' => ['required', 'date'],
            'scans.*.reason' => ['nullable', 'string', 'max:255'],
            'scans.*.forced' => ['boolean'],
            'scans.*.device_id' => ['nullable', 'string', 'max:100'],
            ...self::locationRules('scans.*.'),
        ]);

        $user = $request->user();

        $results = collect($data['scans'])
            ->map(fn (array $item, int $index) => [...$item, 'index' => $index])
            ->sortBy(fn ($item) => Carbon::parse($item['scanned_at'])->getTimestamp())
            ->map(function (array $item) use ($user) {
                $uuid = $item['client_uuid'] ?? null;
                $scannedAt = Carbon::parse($item['scanned_at'])->timezone(config('app.timezone'));

                // La décision prise hors ligne est conservée ; seul le rattachement à l'événement est contrôlé.
                ['outcome' => $outcome, 'method' => $method] = $this->scanner->resolve($user, $item['code'] ?? null, $item['plate'] ?? null);

                // Lot renvoyé : reconnu par client_uuid, ou à défaut par agent + pass + heure exacte + sens.
                $alreadyReceived = $uuid
                    ? $this->existing($uuid)
                    : Scan::where('user_id', $user->id)
                        ->where('pass_id', $outcome->pass?->id)
                        ->where('scanned_at', $scannedAt)
                        ->where('direction', $item['direction'])
                        ->exists();

                if ($alreadyReceived) {
                    return ['index' => $item['index'], 'client_uuid' => $uuid, 'status' => 'duplicate'];
                }

                $this->scanner->record($user, $outcome->pass, ScanResult::from($item['result']), [
                    'direction' => Direction::from($item['direction']),
                    'method' => $method,
                    'reason' => $item['reason'] ?? ($outcome->pass ? null : $outcome->reason),
                    'forced' => $item['forced'] ?? false,
                    'offline' => true,
                    'device_id' => $item['device_id'] ?? null,
                    'client_uuid' => $uuid,
                    ...self::location($item),
                    'scanned_at' => $scannedAt,
                ]);

                return ['index' => $item['index'], 'client_uuid' => $uuid, 'status' => 'created'];
            })
            ->sortBy('index')
            ->values();

        app(AuditLogger::class)->record('scan.batch', "Envoi de {$results->count()} passage(s) effectués hors ligne", properties: [
            'crees' => $results->where('status', 'created')->count(),
            'doublons' => $results->where('status', 'duplicate')->count(),
        ]);

        return response()->json([
            'created' => $results->where('status', 'created')->count(),
            'duplicates' => $results->where('status', 'duplicate')->count(),
            'results' => $results,
        ]);
    }

    /**
     * Historique des passages effectués par l'utilisateur connecté.
     */
    public function history(Request $request): AnonymousResourceCollection
    {
        $scans = Scan::where('user_id', $request->user()->id)
            ->with(['event', 'pass.type', 'pass.vehicle'])
            ->latest('scanned_at')
            ->latest('id')
            ->paginate(30);

        return ScanResource::collection($scans);
    }

    /**
     * Le véhicule est identifié par le contenu du QR code, ou par son immatriculation
     * saisie par l'agent (n'importe quel format : espaces, tirets et casse sont ignorés).
     *
     * @return array<string, list<string>>
     */
    private static function identificationRules(): array
    {
        return [
            'code' => ['required_without:plate', 'nullable', 'string', 'max:500'],
            'plate' => ['required_without:code', 'nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @param  Collection<int, Pass>  $matches
     * @return list<array{event: array{id: int, name: string}, pass: PassResource}>
     */
    private function matches(Collection $matches): array
    {
        return $matches->map(fn (Pass $pass) => [
            'event' => $pass->event->only(['id', 'name']),
            'pass' => new PassResource($pass),
        ])->values()->all();
    }

    /**
     * Position GPS de l'agent au moment du scan (latitude et longitude vont ensemble).
     *
     * @return array<string, list<string>>
     */
    private static function locationRules(string $prefix = ''): array
    {
        return [
            $prefix.'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:'.$prefix.'longitude'],
            $prefix.'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:'.$prefix.'latitude'],
            $prefix.'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{latitude: ?float, longitude: ?float, location_accuracy: ?float}
     */
    private static function location(array $data): array
    {
        return [
            'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
            'location_accuracy' => isset($data['accuracy']) ? (float) $data['accuracy'] : null,
        ];
    }

    /**
     * Passage identique (même agent, même pass, même résultat) enregistré il y a moins de
     * `parking.scan_dedup_seconds` secondes.
     */
    private function recentDuplicate(User $user, ?Pass $pass, ScanResult $result): ?Scan
    {
        if (! $pass) {
            return null;
        }

        return Scan::where('user_id', $user->id)
            ->where('pass_id', $pass->id)
            ->where('result', $result)
            ->where('offline', false)
            ->where('scanned_at', '>=', now()->subSeconds(config('parking.scan_dedup_seconds')))
            ->latest('id')
            ->first();
    }

    private function existing(?string $clientUuid): ?Scan
    {
        return $clientUuid ? Scan::where('client_uuid', $clientUuid)->first() : null;
    }

    private function scanResponse(Scan $scan, int $status): JsonResponse
    {
        $scan->load('event', 'pass.type', 'pass.vehicle');

        return response()->json([
            'scan' => new ScanResource($scan),
            'pass' => $scan->pass ? new PassResource($scan->pass) : null,
        ], $status);
    }
}

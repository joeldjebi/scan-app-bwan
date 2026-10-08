<?php

namespace App\Http\Controllers\Api;

use App\Enums\Direction;
use App\Enums\ScanResult;
use App\Http\Controllers\Controller;
use App\Http\Resources\PassResource;
use App\Http\Resources\ScanResource;
use App\Models\Scan;
use App\Services\PassScanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
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
        $request->validate(['code' => ['required', 'string', 'max:500']]);

        $outcome = $this->scanner->check($request->user(), $this->scanner->find($request->code));

        return response()->json([
            'valid' => $outcome->isValid(),
            'reason' => $outcome->reason,
            'message' => $outcome->message(),
            'can_force' => $this->scanner->canForce($request->user(), $outcome),
            'event' => $outcome->pass ? $outcome->pass->event->only(['id', 'name']) : null,
            'pass' => $outcome->pass ? new PassResource($outcome->pass) : null,
        ]);
    }

    /**
     * Étape 2 : l'agent valide l'entrée ou la sortie (sens déduit automatiquement).
     * `result = denied` trace un refus ; `force` permet au chef d'autoriser un pass non valide.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:500'],
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
        $outcome = $this->scanner->check($user, $this->scanner->find($data['code']));
        $result = ScanResult::from($data['result'] ?? ScanResult::Granted->value);
        $forced = false;

        if ($result === ScanResult::Granted && ! $outcome->isValid()) {
            $canForce = $this->scanner->canForce($user, $outcome);

            if (! ($canForce && ($data['force'] ?? false))) {
                return response()->json([
                    'message' => $outcome->message(),
                    'reason' => $outcome->reason,
                    'can_force' => $canForce,
                ], 422);
            }

            $forced = true;
        }

        $scan = $this->scanner->record($user, $outcome->pass, $result, [
            'direction' => isset($data['direction']) ? Direction::from($data['direction']) : null,
            'reason' => $data['reason'] ?? $outcome->reason,
            'forced' => $forced,
            'device_id' => $data['device_id'] ?? null,
            'client_uuid' => $data['client_uuid'] ?? null,
            ...self::location($data),
        ]);

        return $this->scanResponse($scan, 201);
    }

    /**
     * Envoi des passages effectués hors ligne. Idempotent grâce à `client_uuid`.
     */
    public function batch(Request $request): JsonResponse
    {
        $data = $request->validate([
            'scans' => ['required', 'array', 'max:500'],
            'scans.*.client_uuid' => ['required', 'uuid', 'distinct'],
            'scans.*.code' => ['required', 'string', 'max:500'],
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
            ->sortBy(fn ($item) => Carbon::parse($item['scanned_at'])->getTimestamp())
            ->map(function (array $item) use ($user) {
                if ($this->existing($item['client_uuid'])) {
                    return ['client_uuid' => $item['client_uuid'], 'status' => 'duplicate'];
                }

                // La décision prise hors ligne est conservée ; seul le rattachement à l'événement est contrôlé.
                $outcome = $this->scanner->check($user, $this->scanner->find($item['code']));

                $this->scanner->record($user, $outcome->pass, ScanResult::from($item['result']), [
                    'direction' => Direction::from($item['direction']),
                    'reason' => $item['reason'] ?? ($outcome->pass ? null : $outcome->reason),
                    'forced' => $item['forced'] ?? false,
                    'offline' => true,
                    'device_id' => $item['device_id'] ?? null,
                    'client_uuid' => $item['client_uuid'],
                    ...self::location($item),
                    'scanned_at' => Carbon::parse($item['scanned_at'])->timezone(config('app.timezone')),
                ]);

                return ['client_uuid' => $item['client_uuid'], 'status' => 'created'];
            })
            ->values();

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

<?php

namespace App\Http\Controllers\Api;

use App\Enums\Direction;
use App\Enums\ScanMethod;
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
     * Historique de l'utilisateur connecté : cumul de ses passages et regroupement par véhicule
     * (un élément par pass, du plus récemment vu au plus ancien). Les QR codes inconnus ou hors
     * de ses événements forment un groupe sans pass.
     */
    public function history(Request $request): JsonResponse
    {
        $request->validate([
            'event' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $scans = fn () => Scan::query()
            ->where('user_id', $request->user()->id)
            ->when($request->event, fn ($query, $event) => $query->where('event_id', $event))
            ->when($request->from, fn ($query, $from) => $query->where('scanned_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($request->to, fn ($query, $to) => $query->where('scanned_at', '<=', Carbon::parse($to)->endOfDay()));

        $counters = fn ($query) => $query
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when result = ? and direction = ? then 1 else 0 end) as entries', [ScanResult::Granted->value, Direction::In->value])
            ->selectRaw('sum(case when result = ? and direction = ? then 1 else 0 end) as exits', [ScanResult::Granted->value, Direction::Out->value])
            ->selectRaw('sum(case when result = ? then 1 else 0 end) as denied', [ScanResult::Denied->value])
            ->selectRaw('sum(case when method = ? then 1 else 0 end) as manual', [ScanMethod::Plate->value]);

        $summary = $counters($scans()->toBase())->selectRaw('count(distinct pass_id) as vehicles')->first();

        $groups = $counters($scans()->toBase()->select('pass_id'))
            ->selectRaw('max(scanned_at) as last_scanned_at')
            ->groupBy('pass_id')
            ->orderByDesc('last_scanned_at')
            ->paginate(20);

        $passIds = collect($groups->items())->pluck('pass_id');
        $passes = Pass::with(['type', 'vehicle', 'event'])->whereIn('id', $passIds->filter())->get()->keyBy('id');
        $scansByPass = $scans()
            ->where(fn ($query) => $query->whereIn('pass_id', $passIds->filter())
                ->when($passIds->contains(null), fn ($sub) => $sub->orWhereNull('pass_id')))
            ->latest('scanned_at')
            ->latest('id')
            ->get()
            ->groupBy(fn (Scan $scan) => (string) $scan->pass_id);

        $vehicles = collect($groups->items())->map(function (object $group) use ($passes, $scansByPass) {
            $pass = $group->pass_id ? $passes->get($group->pass_id) : null;

            return [
                'pass' => $pass ? [
                    'id' => $pass->id,
                    'number' => $pass->number,
                    'status' => $pass->status,
                    'presence' => $pass->presence,
                    'type' => ['name' => $pass->type->name, 'color' => $pass->type->color],
                ] : null,
                'vehicle' => $pass?->vehicle ? [
                    'plate' => $pass->vehicle->plate,
                    'plate_key' => $pass->vehicle->plate_key,
                    'brand' => $pass->vehicle->brand,
                    'color' => $pass->vehicle->color,
                ] : null,
                'event' => $pass ? $pass->event->only(['id', 'name']) : null,
                'counts' => self::counts($group),
                'last_scanned_at' => Carbon::parse($group->last_scanned_at)->toISOString(),
                'scans' => $scansByPass->get((string) $group->pass_id, collect())->map(fn (Scan $scan) => [
                    'id' => $scan->id,
                    'direction' => $scan->direction,
                    'result' => $scan->result,
                    'method' => $scan->method,
                    'reason' => $scan->reason,
                    'forced' => $scan->forced,
                    'offline' => $scan->offline,
                    'scanned_at' => $scan->scanned_at,
                    'location' => $scan->hasLocation() ? [
                        'latitude' => $scan->latitude,
                        'longitude' => $scan->longitude,
                        'accuracy' => $scan->location_accuracy,
                    ] : null,
                ])->values(),
            ];
        });

        return response()->json([
            'summary' => [...self::counts($summary), 'vehicles' => (int) $summary->vehicles],
            'data' => $vehicles->values(),
            'links' => [
                'next' => $groups->nextPageUrl(),
                'prev' => $groups->previousPageUrl(),
            ],
            'meta' => [
                'current_page' => $groups->currentPage(),
                'last_page' => $groups->lastPage(),
                'per_page' => $groups->perPage(),
                'total' => $groups->total(),
            ],
        ]);
    }

    /**
     * @return array{total: int, entries: int, exits: int, denied: int, manual: int}
     */
    private static function counts(object $row): array
    {
        return [
            'total' => (int) $row->total,
            'entries' => (int) $row->entries,
            'exits' => (int) $row->exits,
            'denied' => (int) $row->denied,
            'manual' => (int) $row->manual,
        ];
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

<?php

namespace App\Http\Controllers\Api\Partner;

use App\Enums\Direction;
use App\Enums\ScanResult;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticatePartner;
use App\Http\Resources\PartnerPassResource;
use App\Http\Resources\PartnerScanResource;
use App\Models\Partner;
use App\Models\Pass;
use App\Models\Scan;
use App\Services\PassScanner;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Scan des pass par un partenaire extérieur : mêmes règles que pour un agent, limitées aux
 * événements auxquels le partenaire a accès. Un partenaire ne peut jamais forcer un passage.
 */
class ScanController extends Controller
{
    public function __construct(private PassScanner $scanner) {}

    /**
     * Vérifie un QR code ou une immatriculation. N'enregistre rien.
     */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(self::identificationRules());
        $partner = AuthenticatePartner::partner($request);

        ['outcome' => $outcome, 'method' => $method, 'matches' => $matches] = $this->scanner->resolve($partner, $data['code'] ?? null, $data['plate'] ?? null);

        return response()->json([
            'valid' => $outcome->isValid(),
            'reason' => $outcome->reason,
            'message' => $outcome->message(),
            'method' => $method,
            'pass' => $outcome->pass ? new PartnerPassResource($outcome->pass) : null,
            'matches' => $this->matches($matches),
        ]);
    }

    /**
     * Enregistre une entrée ou une sortie (sens déduit si absent) et met à jour la présence du
     * véhicule. `result = denied` trace un refus. L'en-tête `Idempotency-Key` rend l'appel rejouable.
     */
    public function store(Request $request): JsonResponse
    {
        $request->merge(['idempotency_key' => $request->header('Idempotency-Key')]);

        $data = $request->validate([
            ...self::identificationRules(),
            'result' => ['nullable', Rule::enum(ScanResult::class)],
            'direction' => ['nullable', Rule::enum(Direction::class)],
            'reason' => ['nullable', 'string', 'max:255'],
            'operator_ref' => ['nullable', 'string', 'max:100'],
            'device_id' => ['nullable', 'string', 'max:100'],
            'idempotency_key' => ['nullable', 'string', 'max:100'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);

        $partner = AuthenticatePartner::partner($request);
        $idempotencyKey = $data['idempotency_key'] ?? null;

        if ($existing = $this->existing($partner, $idempotencyKey)) {
            return $this->scanResponse($existing, 200);
        }

        ['outcome' => $outcome, 'method' => $method, 'matches' => $matches] = $this->scanner->resolve($partner, $data['code'] ?? null, $data['plate'] ?? null);
        $result = ScanResult::from($data['result'] ?? ScanResult::Granted->value);

        if ($matches->isNotEmpty()) {
            return response()->json([
                'message' => $outcome->message(),
                'code' => $outcome->reason,
                'matches' => $this->matches($matches),
            ], 422);
        }

        if ($result === ScanResult::Granted && ! $outcome->isValid()) {
            return response()->json([
                'message' => $outcome->message(),
                'code' => $outcome->reason,
                'matches' => [],
            ], 422);
        }

        // Même validation renvoyée sans clé d'idempotence (réseau instable, double appui).
        if (! $idempotencyKey && ($duplicate = $this->recentDuplicate($partner, $outcome->pass, $result))) {
            return $this->scanResponse($duplicate, 200);
        }

        try {
            $scan = $this->scanner->record($partner, $outcome->pass, $result, [
                'direction' => isset($data['direction']) ? Direction::from($data['direction']) : null,
                'method' => $method,
                'reason' => $data['reason'] ?? $outcome->reason,
                'operator_ref' => $data['operator_ref'] ?? null,
                'device_id' => $data['device_id'] ?? null,
                'idempotency_key' => $idempotencyKey,
                'latitude' => isset($data['latitude']) ? (float) $data['latitude'] : null,
                'longitude' => isset($data['longitude']) ? (float) $data['longitude'] : null,
                'location_accuracy' => isset($data['accuracy']) ? (float) $data['accuracy'] : null,
            ]);
        } catch (UniqueConstraintViolationException) {
            // Deux envois simultanés avec la même clé d'idempotence : le premier l'emporte.
            return $this->scanResponse($this->existing($partner, $idempotencyKey), 200);
        }

        return $this->scanResponse($scan, 201);
    }

    /**
     * Historique des passages du partenaire, du plus récent au plus ancien, avec le cumul
     * correspondant aux filtres.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'event' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'result' => ['nullable', Rule::enum(ScanResult::class)],
            'direction' => ['nullable', Rule::enum(Direction::class)],
            'operator_ref' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $scans = fn () => AuthenticatePartner::partner($request)->scans()
            ->when($request->event, fn ($query, $event) => $query->where('event_id', $event))
            ->when($request->from, fn ($query, $from) => $query->where('scanned_at', '>=', Carbon::parse($from)))
            ->when($request->to, fn ($query, $to) => $query->where('scanned_at', '<=', Carbon::parse($to)->endOfDay()))
            ->when($request->result, fn ($query, $result) => $query->where('result', $result))
            ->when($request->direction, fn ($query, $direction) => $query->where('direction', $direction))
            ->when($request->operator_ref, fn ($query, $operator) => $query->where('operator_ref', $operator));

        $summary = $scans()->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when result = ? and direction = ? then 1 else 0 end) as entries', [ScanResult::Granted->value, Direction::In->value])
            ->selectRaw('sum(case when result = ? and direction = ? then 1 else 0 end) as exits', [ScanResult::Granted->value, Direction::Out->value])
            ->selectRaw('sum(case when result = ? then 1 else 0 end) as denied', [ScanResult::Denied->value])
            ->first();

        $page = $scans()
            ->with(['event', 'pass.type', 'pass.vehicle'])
            ->orderByDesc('scanned_at')
            ->orderByDesc('id')
            ->cursorPaginate($request->integer('per_page', 50))
            ->withQueryString();

        return response()->json([
            'summary' => [
                'total' => (int) $summary->total,
                'entries' => (int) $summary->entries,
                'exits' => (int) $summary->exits,
                'denied' => (int) $summary->denied,
            ],
            'data' => PartnerScanResource::collection($page->items()),
            'links' => ['next' => $page->nextPageUrl(), 'prev' => $page->previousPageUrl()],
            'meta' => ['per_page' => $page->perPage()],
        ]);
    }

    public function show(Request $request, Scan $scan): JsonResponse
    {
        if ($scan->partner_id !== AuthenticatePartner::partner($request)->id) {
            return response()->json(['message' => 'Passage introuvable.', 'code' => 'not_found'], 404);
        }

        return response()->json(['scan' => new PartnerScanResource($scan->load('event', 'pass.type', 'pass.vehicle'))]);
    }

    /**
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
     * @return list<PartnerPassResource>
     */
    private function matches(Collection $matches): array
    {
        return $matches->map(fn (Pass $pass) => new PartnerPassResource($pass))->values()->all();
    }

    private function existing(Partner $partner, ?string $idempotencyKey): ?Scan
    {
        return $idempotencyKey ? $partner->scans()->where('idempotency_key', $idempotencyKey)->first() : null;
    }

    private function recentDuplicate(Partner $partner, ?Pass $pass, ScanResult $result): ?Scan
    {
        if (! $pass) {
            return null;
        }

        return $partner->scans()
            ->where('pass_id', $pass->id)
            ->where('result', $result)
            ->where('scanned_at', '>=', now()->subSeconds(config('parking.scan_dedup_seconds')))
            ->latest('id')
            ->first();
    }

    private function scanResponse(Scan $scan, int $status): JsonResponse
    {
        $scan->load('event', 'pass.type', 'pass.vehicle');

        return response()->json([
            'scan' => new PartnerScanResource($scan),
            'pass' => $scan->pass ? new PartnerPassResource($scan->pass->load('event')) : null,
        ], $status);
    }
}

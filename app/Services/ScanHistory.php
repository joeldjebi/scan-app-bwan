<?php

namespace App\Services;

use App\Enums\Direction;
use App\Enums\ScanMethod;
use App\Enums\ScanResult;
use App\Models\Event;
use App\Models\Scan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Historique filtré des passages d'un auteur (agent ou partenaire) : liste paginée, cumul et carte.
 */
class ScanHistory
{
    /**
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'event' => ['nullable', 'integer'],
            'result' => ['nullable', 'in:granted,denied'],
            'direction' => ['nullable', 'in:in,out'],
            'method' => ['nullable', 'in:qr,plate'],
            'operator' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }

    /**
     * @param  Builder<Scan>  $base  passages de l'auteur
     * @return array{scans: LengthAwarePaginator, summary: array<string, int>, points: Collection<int, array<string, mixed>>, events: Collection<int, Event>}
     */
    public function build(Request $request, Builder $base): array
    {
        $filtered = fn (): Builder => (clone $base)
            ->when($request->event, fn ($query, $event) => $query->where('event_id', $event))
            ->when($request->result, fn ($query, $result) => $query->where('result', $result))
            ->when($request->direction, fn ($query, $direction) => $query->where('direction', $direction))
            ->when($request->method, fn ($query, $method) => $query->where('method', $method))
            ->when($request->operator, fn ($query, $operator) => $query->where('operator_ref', $operator))
            ->when($request->from, fn ($query, $from) => $query->where('scanned_at', '>=', $from))
            ->when($request->to, fn ($query, $to) => $query->where('scanned_at', '<=', $to.' 23:59:59'));

        $scans = $filtered()
            ->with(['event', 'partner', 'pass.type', 'pass.vehicle'])
            ->latest('scanned_at')
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        $counts = $filtered()->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when result = ? and direction = ? then 1 else 0 end) as entries', [ScanResult::Granted->value, Direction::In->value])
            ->selectRaw('sum(case when result = ? and direction = ? then 1 else 0 end) as exits', [ScanResult::Granted->value, Direction::Out->value])
            ->selectRaw('sum(case when result = ? then 1 else 0 end) as denied', [ScanResult::Denied->value])
            ->selectRaw('sum(case when forced = ? then 1 else 0 end) as forced', [true])
            ->selectRaw('sum(case when method = ? then 1 else 0 end) as manual', [ScanMethod::Plate->value])
            ->selectRaw('sum(case when latitude is not null then 1 else 0 end) as located')
            ->first();

        // Points de la carte : les 500 derniers scans géolocalisés du filtre.
        $points = $filtered()
            ->whereNotNull('latitude')
            ->with('pass')
            ->latest('scanned_at')
            ->limit(500)
            ->get()
            ->map(fn (Scan $scan) => [
                'lat' => $scan->latitude,
                'lng' => $scan->longitude,
                'accuracy' => $scan->location_accuracy,
                'granted' => $scan->result === ScanResult::Granted,
                'label' => sprintf('%s · %s · %s', $scan->scanned_at->format('d/m H:i'), $scan->direction->label(), $scan->pass?->number ?? 'QR inconnu'),
            ]);

        return [
            'scans' => $scans,
            'summary' => array_map('intval', (array) $counts),
            'points' => $points,
            'events' => Event::whereIn('id', (clone $base)->select('event_id'))->orderByDesc('starts_at')->get(['id', 'name']),
        ];
    }
}

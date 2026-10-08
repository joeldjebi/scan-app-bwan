<?php

namespace App\Services;

use App\Enums\Direction;
use App\Enums\ScanResult;
use App\Models\Pass;
use App\Models\Scan;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DeletionService
{
    /**
     * Supprime des pass avec leur véhicule et leurs passages. Le pass lui-même est
     * conservé en soft delete pour que la synchronisation hors ligne signale sa suppression.
     *
     * @param  Collection<int, Pass>  $passes
     * @return int nombre de pass supprimés
     */
    public function deletePasses(Collection $passes): int
    {
        if ($passes->isEmpty()) {
            return 0;
        }

        $numbers = Pass::whereIn('id', $passes->pluck('id'))->orderBy('id')->pluck('number');
        $event = Pass::find($passes->first()->id)?->event;

        app(AuditLogger::class)->record('pass.deleted', "{$numbers->count()} pass supprimé(s), avec leur véhicule et leurs passages", $event, [
            'nombre' => $numbers->count(),
            'numeros' => $numbers->take(200)->all(),
        ]);

        return DB::transaction(function () use ($passes) {
            foreach ($passes->pluck('id')->chunk(500) as $ids) {
                DB::table('vehicles')->whereIn('pass_id', $ids)->delete();
                Scan::whereIn('pass_id', $ids)->delete();
                Pass::whereIn('id', $ids)->update(['deleted_at' => now(), 'updated_at' => now()]);
            }

            return $passes->count();
        });
    }

    /**
     * Supprime des passages puis recalcule la position des véhicules concernés.
     *
     * @param  Collection<int, Scan>  $scans
     * @return int nombre de passages supprimés
     */
    public function deleteScans(Collection $scans): int
    {
        if ($scans->isEmpty()) {
            return 0;
        }

        $details = Scan::with(['pass', 'agent'])->whereIn('id', $scans->pluck('id'))->get()
            ->map(fn (Scan $scan) => sprintf('%s · %s · %s · %s', $scan->scanned_at->format('d/m/Y H:i:s'), $scan->pass?->number ?? 'QR inconnu', $scan->direction->label(), $scan->agent?->name ?? '—'));

        app(AuditLogger::class)->record('scan.deleted', "{$details->count()} passage(s) supprimé(s)", Scan::find($scans->first()->id)?->event, [
            'nombre' => $details->count(),
            'passages' => $details->take(200)->all(),
        ]);

        return DB::transaction(function () use ($scans) {
            Scan::whereIn('id', $scans->pluck('id'))->delete();

            Pass::whereIn('id', $scans->pluck('pass_id')->filter()->unique())
                ->get()
                ->each(fn (Pass $pass) => $this->refreshPresence($pass));

            return $scans->count();
        });
    }

    /**
     * La position d'un véhicule est celle de son dernier passage autorisé restant.
     */
    private function refreshPresence(Pass $pass): void
    {
        $lastGranted = $pass->scans()
            ->where('result', ScanResult::Granted)
            ->latest('scanned_at')
            ->latest('id')
            ->first();

        $pass->update([
            'presence' => $lastGranted?->direction ?? Direction::Out,
            'last_scanned_at' => $lastGranted?->scanned_at,
        ]);
    }
}

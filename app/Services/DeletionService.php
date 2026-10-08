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

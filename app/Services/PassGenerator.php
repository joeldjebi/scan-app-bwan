<?php

namespace App\Services;

use App\Models\PassType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PassGenerator
{
    /**
     * Génère $count nouveaux pass pour un type, numérotés à la suite des existants.
     *
     * @return int nombre de pass créés
     */
    public function generate(PassType $type, int $count): int
    {
        $event = $type->event;

        return DB::transaction(function () use ($type, $event, $count) {
            // Verrouille le type pour que deux générations simultanées ne se chevauchent pas.
            PassType::whereKey($type->id)->lockForUpdate()->first();

            // Les pass supprimés gardent leur numéro : la numérotation ne les réutilise jamais.
            $start = (int) $type->passes()->withTrashed()->max('sequence');
            $now = now();
            $width = max(4, strlen((string) ($start + $count)));

            foreach (array_chunk(range($start + 1, $start + $count), 500) as $sequences) {
                $rows = array_map(fn (int $sequence) => [
                    'event_id' => $event->id,
                    'pass_type_id' => $type->id,
                    'token' => $this->token(),
                    'sequence' => $sequence,
                    'number' => sprintf('%s-%s-%s', $event->code, $type->code, str_pad($sequence, $width, '0', STR_PAD_LEFT)),
                    'status' => 'pending',
                    'presence' => 'out',
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $sequences);

                DB::table('passes')->insert($rows);
            }

            return $count;
        });
    }

    private function token(): string
    {
        return Str::random(config('parking.token_length'));
    }
}

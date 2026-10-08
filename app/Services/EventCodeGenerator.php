<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Génère le code court d'un événement : initiales du nom + année sur 2 chiffres
 * (« Afro Summer Festival » en 2026 → ASF26), rendu unique par une lettre (ASF26B…).
 */
class EventCodeGenerator
{
    private const STOP_WORDS = ['de', 'du', 'des', 'la', 'le', 'les', 'et', 'en', 'a', 'au', 'aux', 'd', 'l', 'the', 'of', 'and'];

    public function generate(string $name, ?Carbon $startsAt = null, ?int $ignoreEventId = null): string
    {
        $base = $this->base($name).($startsAt ?? now())->format('y');

        $code = $base;
        $suffix = 'B';
        while ($this->exists($code, $ignoreEventId)) {
            $code = $base.$suffix;
            $suffix = $suffix === 'Z' ? 'A'.random_int(10, 99) : chr(ord($suffix) + 1);
        }

        return $code;
    }

    /**
     * Initiales des mots significatifs (4 max) ; un seul mot donne ses 3 premières lettres.
     */
    private function base(string $name): string
    {
        $words = collect(preg_split('/[^A-Za-z0-9]+/', Str::ascii($name)))
            ->filter()
            ->reject(fn (string $word) => in_array(strtolower($word), self::STOP_WORDS, true))
            ->values();

        $base = match (true) {
            $words->isEmpty() => 'EVT',
            $words->count() === 1 => substr($words->first(), 0, 3),
            default => $words->take(4)->map(fn (string $word) => $word[0])->implode(''),
        };

        return strtoupper($base);
    }

    private function exists(string $code, ?int $ignoreEventId): bool
    {
        return Event::where('code', $code)->when($ignoreEventId, fn ($query) => $query->whereKeyNot($ignoreEventId))->exists();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'plate', 'brand', 'color', 'phone'])]
class Vehicle extends Model
{
    use HasFactory;

    /** La synchronisation hors ligne se base sur passes.updated_at. */
    protected $touches = ['pass'];

    public function pass(): BelongsTo
    {
        return $this->belongsTo(Pass::class);
    }

    /**
     * Immatriculation affichée (majuscules, espaces uniformisés) ; la clé
     * sans séparateurs sert à l'unicité et à la recherche.
     */
    protected function plate(): Attribute
    {
        return Attribute::make(set: fn (string $value) => [
            'plate' => self::formatPlate($value),
            'plate_key' => self::plateKey($value),
        ]);
    }

    public static function formatPlate(string $value): string
    {
        return trim(preg_replace('/[\s\.]+/', ' ', mb_strtoupper(trim($value))));
    }

    public static function plateKey(string $value): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper($value));
    }
}

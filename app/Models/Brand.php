<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Marque de véhicule proposée dans le formulaire d'enregistrement.
 */
#[Fillable(['name', 'is_active'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use Auditable, HasFactory;

    /** Liste des marques proposées, mise en cache et vidée à chaque modification. */
    public const CACHE_KEY = 'brands.active';

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Brand>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    public function auditLabel(): string
    {
        return (string) $this->name;
    }

    protected function auditNoun(): array
    {
        return ['Marque', true];
    }
}

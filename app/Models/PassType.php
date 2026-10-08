<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'color'])]
class PassType extends Model
{
    use Auditable, HasFactory;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function passes(): HasMany
    {
        return $this->hasMany(Pass::class);
    }

    public function auditLabel(): string
    {
        return (string) $this->name;
    }

    protected function auditNoun(): array
    {
        return ['Type de pass', false];
    }
}

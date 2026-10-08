<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Export préparé en arrière-plan. Statuts : pending → processing → done | failed.
 */
#[Fillable(['user_id', 'event_id', 'pass_type_id', 'format', 'status', 'total', 'processed', 'file_path', 'file_name', 'error'])]
class Export extends Model
{
    /** Durée de conservation des fichiers générés. */
    public const RETENTION_HOURS = 24;

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function passType(): BelongsTo
    {
        return $this->belongsTo(PassType::class);
    }

    /**
     * @return array{id: int, status: string, total: int, processed: int, error: ?string, status_url: string, download_url: ?string}
     */
    public function toProgress(): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status,
            'total' => $this->total,
            'processed' => $this->processed,
            'error' => $this->error,
            'status_url' => route('exports.show', $this),
            'download_url' => $this->status === 'done' ? route('exports.download', $this) : null,
        ];
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notification envoyée (ou à envoyer) à l'URL de webhook d'un partenaire.
 */
#[Fillable(['partner_id', 'uuid', 'type', 'payload', 'status', 'attempts', 'response_status', 'last_error', 'delivered_at'])]
class WebhookDelivery extends Model
{
    public const PENDING = 'pending';

    public const DELIVERED = 'delivered';

    public const FAILED = 'failed';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'delivered_at' => 'datetime',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}

<?php

namespace App\Services;

use App\Models\Pass;

/**
 * Résultat de la vérification d'un QR code.
 */
final class ScanOutcome
{
    public const UNKNOWN_PASS = 'unknown_pass';

    public const NOT_ASSIGNED = 'not_assigned';

    public const EVENT_CLOSED = 'event_closed';

    public const NOT_REGISTERED = 'not_registered';

    public const REVOKED = 'revoked';

    private const MESSAGES = [
        self::UNKNOWN_PASS => 'QR code inconnu.',
        self::NOT_ASSIGNED => 'Vous n\'êtes pas affecté à l\'événement de ce pass.',
        self::EVENT_CLOSED => 'L\'événement est clôturé.',
        self::NOT_REGISTERED => 'Aucun véhicule n\'est enregistré sur ce pass.',
        self::REVOKED => 'Ce pass a été révoqué.',
    ];

    public function __construct(
        public readonly ?Pass $pass,
        public readonly ?string $reason = null,
    ) {}

    /**
     * Un chef (ou admin) peut forcer le passage d'un pass de son événement dans ces cas.
     * Un événement clôturé refuse tous les passages, sans exception.
     */
    public function isForceable(): bool
    {
        return in_array($this->reason, [self::NOT_REGISTERED, self::REVOKED], true);
    }

    /**
     * Libellé d'un motif enregistré sur un passage (code connu ou motif libre saisi par l'agent).
     */
    public static function label(?string $reason): ?string
    {
        return self::MESSAGES[$reason] ?? $reason;
    }

    public function isValid(): bool
    {
        return $this->reason === null;
    }

    public function message(): string
    {
        return $this->reason ? self::MESSAGES[$this->reason] : 'Pass valide.';
    }
}

<?php

namespace App\Enums;

enum PassStatus: string
{
    case Pending = 'pending';
    case Registered = 'registered';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente d\'enregistrement',
            self::Registered => 'Enregistré',
            self::Revoked => 'Révoqué',
        };
    }
}

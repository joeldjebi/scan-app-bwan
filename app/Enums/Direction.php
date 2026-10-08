<?php

namespace App\Enums;

/**
 * Sens d'un passage, et position courante d'un véhicule (in = dans le parking).
 */
enum Direction: string
{
    case In = 'in';
    case Out = 'out';

    public function opposite(): self
    {
        return $this === self::In ? self::Out : self::In;
    }

    public function label(): string
    {
        return match ($this) {
            self::In => 'Entrée',
            self::Out => 'Sortie',
        };
    }
}

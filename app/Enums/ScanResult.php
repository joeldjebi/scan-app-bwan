<?php

namespace App\Enums;

enum ScanResult: string
{
    case Granted = 'granted';
    case Denied = 'denied';

    public function label(): string
    {
        return match ($this) {
            self::Granted => 'Autorisé',
            self::Denied => 'Refusé',
        };
    }
}

<?php

namespace App\Enums;

/**
 * Comment le véhicule a été identifié lors d'un passage.
 */
enum ScanMethod: string
{
    case Qr = 'qr';
    case Plate = 'plate';

    public function label(): string
    {
        return match ($this) {
            self::Qr => 'QR code',
            self::Plate => 'Saisie manuelle',
        };
    }
}

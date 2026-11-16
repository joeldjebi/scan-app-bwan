<?php

namespace App\Enums;

/**
 * Droits d'une clé API partenaire.
 */
enum PartnerAbility: string
{
    case Verify = 'scans:verify';
    case Write = 'scans:write';
    case Read = 'scans:read';

    public function label(): string
    {
        return match ($this) {
            self::Verify => 'Vérifier un pass',
            self::Write => 'Enregistrer des passages',
            self::Read => 'Consulter l\'historique',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

<?php

namespace App\Enums;

enum StaffRole: string
{
    case Chief = 'chief';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::Chief => 'Chef agent parking',
            self::Agent => 'Agent parking',
        };
    }
}

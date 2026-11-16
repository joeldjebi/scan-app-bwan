<?php

namespace App\Services;

use App\Models\Event;

/**
 * Auteur d'un passage : agent (utilisateur de l'application mobile) ou partenaire (API partenaire).
 */
interface ScanActor
{
    /**
     * Peut scanner les pass de cet événement.
     */
    public function canOperate(Event $event): bool;

    /**
     * Peut forcer le passage d'un pass non valide de cet événement.
     */
    public function canSupervise(Event $event): bool;
}

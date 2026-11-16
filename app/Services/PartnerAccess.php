<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Partner;
use App\Models\User;

/**
 * Accès d'un partenaire aux événements (fiche partenaire pour l'admin, page de l'événement pour
 * l'admin et le chef). Chaque ouverture ou retrait d'accès est journalisé.
 */
class PartnerAccess
{
    public function __construct(private AuditLogger $audit) {}

    public function grant(Event $event, Partner $partner, ?User $grantedBy): void
    {
        if ($partner->events()->whereKey($event->id)->exists()) {
            return;
        }

        $partner->events()->attach($event->id, ['granted_by' => $grantedBy?->id]);
        $this->audit->record('partner.access_granted', "Accès API ouvert au partenaire « {$partner->name} »", $event, ['partenaire' => $partner->name]);
    }

    public function revoke(Event $event, Partner $partner): void
    {
        if ($partner->events()->detach($event->id)) {
            $this->audit->record('partner.access_revoked', "Accès API retiré au partenaire « {$partner->name} »", $event, ['partenaire' => $partner->name]);
        }
    }

    /**
     * Aligne les accès du partenaire sur la liste d'événements cochés.
     *
     * @param  list<int>  $eventIds
     */
    public function sync(Partner $partner, array $eventIds, ?User $grantedBy): void
    {
        $current = $partner->events()->pluck('events.id')->all();

        Event::whereIn('id', array_diff($eventIds, $current))->get()->each(fn (Event $event) => $this->grant($event, $partner, $grantedBy));
        Event::whereIn('id', array_diff($current, $eventIds))->get()->each(fn (Event $event) => $this->revoke($event, $partner));
    }
}

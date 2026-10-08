<?php

namespace App\Http\Controllers\Web;

use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        $data = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')],
            'role' => ['required', Rule::enum(StaffRole::class)],
        ]);

        $role = StaffRole::from($data['role']);

        if ($role === StaffRole::Chief && count($data['user_ids']) > 1) {
            return back()->with('error', 'Un événement n\'a qu\'un seul chef agent parking.');
        }

        DB::transaction(function () use ($event, $data, $role) {
            if ($role === StaffRole::Chief) {
                $this->demoteCurrentChief($event);
            }

            $event->staff()->syncWithoutDetaching(
                collect($data['user_ids'])->mapWithKeys(fn ($id) => [$id => ['role' => $role->value]])->all()
            );
        });

        return back()->with('success', 'Équipe mise à jour.');
    }

    public function update(Request $request, Event $event, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::enum(StaffRole::class)]]);
        $role = StaffRole::from($data['role']);

        DB::transaction(function () use ($event, $user, $role) {
            if ($role === StaffRole::Chief) {
                $this->demoteCurrentChief($event);
            }

            $event->staff()->updateExistingPivot($user->id, ['role' => $role->value]);
        });

        return back()->with('success', "{$user->name} est maintenant {$role->label()}.");
    }

    public function destroy(Event $event, User $user): RedirectResponse
    {
        $event->staff()->detach($user->id);

        return back()->with('success', "{$user->name} a été retiré de l'équipe.");
    }

    /**
     * Un seul chef par événement : l'ancien chef redevient agent.
     */
    private function demoteCurrentChief(Event $event): void
    {
        $event->staff()->newPivotStatement()
            ->where('event_id', $event->id)
            ->where('role', StaffRole::Chief->value)
            ->update(['role' => StaffRole::Agent->value]);
    }
}

<?php

namespace App\Http\Controllers\Web;

use App\Enums\StaffRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\StaffAssignment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StaffController extends Controller
{
    public function __construct(private StaffAssignment $staff) {}

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

        User::whereIn('id', $data['user_ids'])->get()->each(fn (User $user) => $this->staff->assign($event, $user, $role));

        return back()->with('success', 'Équipe mise à jour.');
    }

    public function update(Request $request, Event $event, User $user): RedirectResponse
    {
        $data = $request->validate(['role' => ['required', Rule::enum(StaffRole::class)]]);
        $role = StaffRole::from($data['role']);

        $this->staff->assign($event, $user, $role);

        return back()->with('success', "{$user->name} est maintenant {$role->label()}.");
    }

    public function destroy(Event $event, User $user): RedirectResponse
    {
        $this->staff->remove($event, $user);

        return back()->with('success', "{$user->name} a été retiré de l'équipe.");
    }
}

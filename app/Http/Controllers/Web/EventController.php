<?php

namespace App\Http\Controllers\Web;

use App\Enums\Direction;
use App\Enums\EventStatus;
use App\Enums\PassStatus;
use App\Enums\ScanResult;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\User;
use App\Services\PosterPalette;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EventController extends Controller
{
    public function create(): View
    {
        return view('events.form', ['event' => new Event(['status' => EventStatus::Draft])]);
    }

    public function store(Request $request): RedirectResponse
    {
        $event = new Event;
        $this->fill($request, $event);

        return redirect()->route('events.show', $event)->with('success', 'Événement créé. Ajoutez maintenant les types de pass.');
    }

    public function show(Event $event): View
    {
        $event->load(['passTypes' => fn ($query) => $query->withCount([
            'passes',
            'passes as registered_count' => fn ($q) => $q->where('status', PassStatus::Registered),
            'passes as inside_count' => fn ($q) => $q->where('presence', Direction::In),
        ]), 'staff']);

        $stats = [
            'total' => $event->passes()->count(),
            'registered' => $event->passes()->where('status', PassStatus::Registered)->count(),
            'revoked' => $event->passes()->where('status', PassStatus::Revoked)->count(),
            'inside' => $event->passes()->where('presence', Direction::In)->count(),
            'entries' => $event->scans()->where('result', ScanResult::Granted)->where('direction', Direction::In)->count(),
            'denied' => $event->scans()->where('result', ScanResult::Denied)->count(),
        ];

        $staffIds = $event->staff->pluck('id');
        $availableAgents = User::where('role', UserRole::Agent)
            ->where('is_active', true)
            ->whereNotIn('id', $staffIds)
            ->orderBy('name')
            ->get();

        $recentScans = $event->scans()->with(['agent', 'pass.vehicle', 'pass.type'])->latest('scanned_at')->limit(10)->get();

        return view('events.show', compact('event', 'stats', 'availableAgents', 'recentScans'));
    }

    public function edit(Event $event): View
    {
        return view('events.form', compact('event'));
    }

    public function update(Request $request, Event $event): RedirectResponse
    {
        $this->fill($request, $event);

        return redirect()->route('events.show', $event)->with('success', 'Événement mis à jour.');
    }

    public function destroy(Event $event): RedirectResponse
    {
        if ($event->scans()->exists()) {
            return back()->with('error', 'Impossible de supprimer un événement qui a déjà des passages. Clôturez-le plutôt.');
        }

        $event->delete();
        Storage::disk('public')->delete(array_filter([$event->logo_path, $event->poster_path]));

        return redirect()->route('dashboard')->with('success', 'Événement supprimé.');
    }

    /**
     * Enregistre les champs, le logo et l'affiche, puis les couleurs du formulaire d'enregistrement.
     */
    private function fill(Request $request, Event $event): void
    {
        $data = $this->validated($request, $event->exists ? $event : null);
        $disk = Storage::disk('public');
        $previous = array_filter([$event->logo_path, $event->poster_path]);

        foreach (['logo' => 'logo_path', 'poster' => 'poster_path'] as $input => $column) {
            if ($request->hasFile($input)) {
                $event->{$column} = $request->file($input)->store('events', 'public');
            } elseif ($request->boolean('remove_'.$input)) {
                $event->{$column} = null;
            }
        }

        $event->fill(collect($data)->except(['logo', 'poster', 'remove_logo', 'remove_poster'])->all());
        $event->theme_from_poster = $request->boolean('theme_from_poster');

        if ($event->theme_from_poster && $event->poster_path && ($palette = app(PosterPalette::class)->extract($disk->path($event->poster_path)))) {
            $event->primary_color = $palette['primary'];
            $event->secondary_color = $palette['secondary'];
        }

        $event->save();

        $disk->delete(array_diff($previous, array_filter([$event->logo_path, $event->poster_path])));
    }

    private function validated(Request $request, ?Event $event = null): array
    {
        $request->merge(['code' => strtoupper((string) $request->code)]);

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // Le code préfixe les numéros de pass : il ne peut plus changer une fois les pass générés.
            'code' => array_filter([
                'required', 'alpha_num', 'max:12',
                Rule::unique('events')->ignore($event),
                $event?->passes()->exists() ? Rule::in([$event->code]) : null,
            ]),
            'location' => ['nullable', 'string', 'max:255'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'status' => ['required', Rule::enum(EventStatus::class)],
            'description' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:'.config('parking.image_max_kb')],
            'poster' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.config('parking.image_max_kb')],
            'remove_logo' => ['boolean'],
            'remove_poster' => ['boolean'],
            'theme_from_poster' => ['boolean'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ], [
            'code.in' => 'Le code ne peut plus être modifié car des pass ont déjà été générés.',
        ]);
    }
}

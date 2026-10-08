<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\PassType;
use App\Services\AuditLogger;
use App\Services\PassGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PassTypeController extends Controller
{
    public function store(Request $request, Event $event): RedirectResponse
    {
        $request->merge(['code' => strtoupper((string) $request->code)]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'alpha_num', 'max:10', Rule::unique('pass_types')->where('event_id', $event->id)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $event->passTypes()->create($data);

        return back()->with('success', "Type « {$data['name']} » ajouté.");
    }

    public function update(Request $request, Event $event, PassType $passType): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $passType->update($data);

        return back()->with('success', 'Type mis à jour.');
    }

    public function destroy(Event $event, PassType $passType): RedirectResponse
    {
        if ($passType->passes()->exists()) {
            return back()->with('error', 'Impossible de supprimer un type qui contient déjà des pass.');
        }

        $passType->delete();

        return back()->with('success', 'Type supprimé.');
    }

    public function generate(Request $request, Event $event, PassType $passType, PassGenerator $generator): RedirectResponse
    {
        $data = $request->validate([
            'count' => ['required', 'integer', 'min:1', 'max:'.config('parking.max_generation')],
        ]);

        $before = (int) $passType->passes()->withTrashed()->max('sequence');
        $count = $generator->generate($passType, $data['count']);
        $created = $passType->passes()->where('sequence', '>', $before)->orderBy('sequence');

        app(AuditLogger::class)->record('pass.generated', "{$count} pass « {$passType->name} » générés", $passType, [
            'nombre' => $count,
            'du' => $created->value('number'),
            'au' => $created->reorder()->orderByDesc('sequence')->value('number'),
        ]);

        return back()->with('success', "{$count} pass « {$passType->name} » générés.");
    }
}

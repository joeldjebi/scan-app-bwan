@php($selectable ??= false)
@php($showEvent ??= false)
@php($showAgent ??= true)
@php($canDelete = auth()->user()->can('admin'))
<div class="overflow-x-auto" x-data="{ all: false, sync() { $dispatch('scan-selection', $root.querySelectorAll('input[name=\'ids[]\']:checked').length) } }">
    <table class="min-w-full text-sm">
        <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
            <tr>
                @if ($selectable && $canDelete)
                    <th class="w-8 px-5 py-2">
                        <input type="checkbox" x-model="all" aria-label="Tout sélectionner"
                               @change="$root.querySelectorAll('input[name=\'ids[]\']').forEach(box => box.checked = all); sync()">
                    </th>
                @endif
                <th class="px-5 py-2 font-medium">Heure</th>
                @if ($showEvent)
                    <th class="px-5 py-2 font-medium">Événement</th>
                @endif
                <th class="px-5 py-2 font-medium">Pass</th>
                <th class="px-5 py-2 font-medium">Véhicule</th>
                <th class="px-5 py-2 font-medium">Sens</th>
                <th class="px-5 py-2 font-medium">Résultat</th>
                @if ($showAgent)
                    <th class="px-5 py-2 font-medium">Agent</th>
                @endif
                <th class="px-5 py-2 font-medium">Position</th>
                @if ($canDelete)
                    <th class="px-5 py-2"></th>
                @endif
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-100">
            @forelse ($scans as $scan)
                <tr>
                    @if ($selectable && $canDelete)
                        <td class="px-5 py-2"><input type="checkbox" name="ids[]" value="{{ $scan->id }}" form="bulk-delete-scans" @change="sync()" aria-label="Sélectionner le passage"></td>
                    @endif
                    <td class="whitespace-nowrap px-5 py-2 text-slate-600">{{ $scan->scanned_at->format('d/m H:i:s') }}</td>
                    @if ($showEvent)
                        <td class="whitespace-nowrap px-5 py-2">{{ $scan->event?->name ?? '—' }}</td>
                    @endif
                    <td class="whitespace-nowrap px-5 py-2">
                        @if ($scan->pass)
                            <a href="{{ route('events.passes.show', [$scan->event_id, $scan->pass]) }}" class="font-mono text-indigo-600 hover:underline">{{ $scan->pass->number }}</a>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap px-5 py-2 font-mono">{{ $scan->pass?->vehicle?->plate ?? '—' }}</td>
                    <td class="px-5 py-2">{{ $scan->direction->label() }}</td>
                    <td class="px-5 py-2">
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $scan->result === \App\Enums\ScanResult::Granted ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800' }}">{{ $scan->result->label() }}</span>
                        @if ($scan->forced) <span class="ml-1 rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">forcé</span> @endif
                        @if ($scan->offline) <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-600">hors ligne</span> @endif
                        @if ($scan->reason) <span class="ml-1 text-xs text-slate-500">{{ \App\Services\ScanOutcome::label($scan->reason) }}</span> @endif
                    </td>
                    @if ($showAgent)
                        <td class="whitespace-nowrap px-5 py-2 text-slate-600">
                            @if ($scan->agent && auth()->user()->can('admin'))
                                <a href="{{ route('users.scans', $scan->agent) }}" class="hover:text-indigo-600 hover:underline">{{ $scan->agent->name }}</a>
                            @else
                                {{ $scan->agent?->name ?? '—' }}
                            @endif
                        </td>
                    @endif
                    <td class="whitespace-nowrap px-5 py-2">
                        @if ($scan->hasLocation())
                            <a href="{{ $scan->mapUrl() }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-indigo-600 hover:underline" title="Précision : {{ $scan->location_accuracy ? '±'.$scan->location_accuracy.' m' : 'inconnue' }}">
                                <svg class="size-3.5" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18s6-5.33 6-10A6 6 0 0 0 4 8c0 4.67 6 10 6 10Zm0-7.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" clip-rule="evenodd"/></svg>
                                Carte
                            </a>
                        @else
                            <span class="text-slate-400">—</span>
                        @endif
                    </td>
                    @if ($canDelete)
                        <td class="px-5 py-2 text-right">
                            @if ($scan->event_id)
                            <form method="POST" action="{{ route('events.scans.destroy', [$scan->event_id, $scan]) }}" onsubmit="return confirm('Supprimer ce passage ? La position du véhicule sera recalculée.')">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:underline">Supprimer</button>
                            </form>
                            @endif
                        </td>
                    @endif
                </tr>
            @empty
                <tr><td colspan="10" class="px-5 py-6 text-center text-slate-500">Aucun passage.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>

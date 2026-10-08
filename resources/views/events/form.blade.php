@extends('layouts.app', ['title' => $event->exists ? 'Modifier l\'événement' : 'Nouvel événement'])

@php($input = 'mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-indigo-500 focus:ring-2 focus:ring-indigo-200 focus:outline-none')

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="mb-6 text-2xl font-bold">{{ $event->exists ? 'Modifier l\'événement' : 'Nouvel événement' }}</h1>

        <form method="POST" action="{{ $event->exists ? route('events.update', $event) : route('events.store') }}" enctype="multipart/form-data" class="space-y-5 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf
            @if ($event->exists) @method('PUT') @endif

            @php($codeLocked = $event->exists && $event->passes()->exists())
            <div class="grid gap-5 sm:grid-cols-3"
                 x-data="{
                     name: @js(old('name', $event->name)),
                     code: @js(old('code', $event->code)),
                     auto: @js(! $event->exists && ! old('code')),
                     loading: false,
                     timer: null,
                     suggest() {
                         if (!this.auto || @js($codeLocked)) return;
                         clearTimeout(this.timer);
                         this.timer = setTimeout(async () => {
                             if (!this.name.trim()) { this.code = ''; return; }
                             this.loading = true;
                             const params = new URLSearchParams({ name: this.name, starts_at: document.querySelector('[name=starts_at]')?.value ?? '', event: @js($event->id ?? '') });
                             try {
                                 const response = await fetch(@js(route('events.code-suggestion')) + '?' + params, { headers: { Accept: 'application/json' } });
                                 if (response.ok && this.auto) { this.code = (await response.json()).code; }
                             } finally { this.loading = false; }
                         }, 350);
                     },
                 }"
                 @starts-at-changed.window="suggest()">
                <div class="sm:col-span-2">
                    <label class="block text-sm font-medium text-slate-700">Nom de l'événement</label>
                    <input name="name" x-model="name" @input="suggest()" required class="{{ $input }}">
                </div>
                <div>
                    <label class="flex items-center justify-between text-sm font-medium text-slate-700">
                        Code court
                        <span x-show="loading" x-cloak class="size-3.5 animate-spin rounded-full border-2 border-slate-300 border-t-indigo-600" aria-label="Calcul du code"></span>
                    </label>
                    <input name="code" x-model="code" maxlength="12" placeholder="Automatique" @input="auto = false"
                           @readonly($codeLocked) class="{{ $input }} font-mono uppercase {{ $codeLocked ? 'bg-slate-100 text-slate-500' : '' }}">
                    @if ($codeLocked)
                        <p class="mt-1 text-xs text-slate-500">Verrouillé : des pass ont déjà été générés.</p>
                    @else
                        <p class="mt-1 text-xs text-slate-500">
                            <span x-show="auto">Généré automatiquement depuis le nom.</span>
                            <span x-show="!auto" x-cloak>Personnalisé · <button type="button" class="text-indigo-600 hover:underline" @click="auto = true; suggest()">régénérer</button></span>
                        </p>
                    @endif
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Lieu</label>
                <input name="location" value="{{ old('location', $event->location) }}" class="{{ $input }}">
            </div>

            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="block text-sm font-medium text-slate-700">Début</label>
                    <input type="datetime-local" name="starts_at" value="{{ old('starts_at', $event->starts_at?->format('Y-m-d\TH:i')) }}" required class="{{ $input }}" @change="$dispatch('starts-at-changed')">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700">Fin</label>
                    <input type="datetime-local" name="ends_at" value="{{ old('ends_at', $event->ends_at?->format('Y-m-d\TH:i')) }}" required class="{{ $input }}">
                </div>
            </div>

            <div>
                <label class="block text-sm font-medium text-slate-700">Statut</label>
                <select name="status" class="{{ $input }}">
                    @foreach (\App\Enums\EventStatus::cases() as $status)
                        <option value="{{ $status->value }}" @selected(old('status', $event->status?->value) === $status->value)>{{ $status->label() }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">Un événement clôturé refuse tous les scans et n'apparaît plus dans l'application.</p>
            </div>

            <fieldset class="space-y-4 rounded-xl border border-slate-200 p-4"
                      x-data="{ auto: @js((bool) old('theme_from_poster', $event->exists ? $event->theme_from_poster : true)) }">
                <legend class="px-1 text-sm font-semibold text-slate-800">Visuels du formulaire d'enregistrement</legend>
                <p class="text-xs text-slate-500">Le logo et l'affiche habillent la page que l'usager ouvre en scannant son QR code. JPG, PNG ou WebP, {{ config('parking.image_max_kb') / 1024 }} Mo maximum.</p>

                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach (['logo' => ['Logo', $event->logoUrl(), 'h-20 w-20 object-contain'], 'poster' => ['Affiche', $event->posterUrl(), 'h-40 w-28 object-cover']] as $field => [$label, $url, $previewClass])
                        <div x-data="{ preview: @js($url), removed: false }">
                            <label class="block text-sm font-medium text-slate-700">{{ $label }}</label>
                            <div class="mt-1 flex items-start gap-3">
                                <div class="flex shrink-0 items-center justify-center overflow-hidden rounded-lg border border-dashed border-slate-300 bg-slate-50 {{ $previewClass }}">
                                    <template x-if="preview && !removed"><img :src="preview" alt="" class="{{ $previewClass }}"></template>
                                    <span x-show="!preview || removed" class="px-2 text-center text-[11px] text-slate-400">Aucun{{ $field === 'poster' ? 'e' : '' }} {{ strtolower($label) }}</span>
                                </div>
                                <div class="min-w-0 space-y-2 text-sm">
                                    <input type="file" name="{{ $field }}" accept="image/png,image/jpeg,image/webp{{ $field === 'logo' ? ',image/svg+xml' : '' }}"
                                           @change="const file = $event.target.files[0]; if (file) { preview = URL.createObjectURL(file); removed = false; }"
                                           class="block w-full text-xs file:mr-2 file:rounded-md file:border-0 file:bg-slate-900 file:px-3 file:py-1.5 file:text-xs file:font-semibold file:text-white">
                                    @if ($url)
                                        <label class="flex items-center gap-2 text-xs text-red-600">
                                            <input type="checkbox" name="remove_{{ $field }}" value="1" x-model="removed" class="rounded border-slate-300"> Retirer
                                        </label>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="border-t border-slate-100 pt-4">
                    <label class="flex items-center gap-2 text-sm">
                        <input type="hidden" name="theme_from_poster" value="0">
                        <input type="checkbox" name="theme_from_poster" value="1" x-model="auto" class="rounded border-slate-300">
                        Couleurs automatiques, extraites de l'affiche
                    </label>
                    <div x-show="!auto" x-cloak class="mt-3 flex flex-wrap gap-4">
                        @foreach (['primary_color' => 'Couleur principale', 'secondary_color' => 'Couleur d\'accent'] as $field => $label)
                            <label class="flex items-center gap-2 text-sm text-slate-700">
                                <input type="color" name="{{ $field }}" value="{{ old($field, $event->{$field} ?? ($field === 'primary_color' ? '#4f46e5' : '#1e1b4b')) }}" class="h-9 w-12 rounded border border-slate-300">
                                {{ $label }}
                            </label>
                        @endforeach
                    </div>
                    @if ($event->primary_color)
                        <p class="mt-3 flex items-center gap-2 text-xs text-slate-500">
                            Couleurs actuelles :
                            <span class="size-4 rounded" style="background: {{ $event->primary_color }}"></span>
                            <span class="size-4 rounded" style="background: {{ $event->secondary_color }}"></span>
                        </p>
                    @endif
                </div>
            </fieldset>

            <div>
                <label class="block text-sm font-medium text-slate-700">Description</label>
                <textarea name="description" rows="3" class="{{ $input }}">{{ old('description', $event->description) }}</textarea>
            </div>

            <div class="flex justify-end gap-3">
                <a href="{{ $event->exists ? route('events.show', $event) : route('dashboard') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Annuler</a>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Enregistrer</button>
            </div>
        </form>

        @if ($event->exists)
            <form method="POST" action="{{ route('events.destroy', $event) }}" class="mt-6 text-right" onsubmit="return confirm('Supprimer définitivement cet événement et tous ses pass ?')">
                @csrf @method('DELETE')
                <button class="text-sm text-red-600 hover:underline">Supprimer l'événement</button>
            </form>
        @endif
    </div>
@endsection

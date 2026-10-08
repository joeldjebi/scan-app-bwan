{{-- Export des QR codes par lots, générés à la demande. Paramètres : $event, $type (facultatif), $count --}}
<div class="relative" x-data="{ open: false }" @keydown.escape="open = false">
    <button type="button" @click="open = !open" class="{{ $buttonClass ?? 'rounded-lg border border-slate-300 px-3 py-2 text-sm hover:bg-slate-50' }}">{{ $label ?? 'QR codes ▾' }}</button>
    <form x-show="open" x-cloak @click.outside="open = false" method="GET" action="{{ route('events.export.qrcodes.plan', $event) }}"
          data-qr-export="QR codes{{ isset($type) ? ' « '.$type->name.' »' : '' }}"
          class="absolute {{ $align ?? 'right-0' }} z-20 mt-1 w-64 space-y-3 rounded-xl border border-slate-200 bg-white p-4 text-left text-sm shadow-xl">
        @isset($type)
            <input type="hidden" name="type" value="{{ $type->id }}">
        @endisset
        <p class="font-semibold">Exporter {{ number_format($count, 0, ',', ' ') }} QR code(s)</p>
        <fieldset>
            <legend class="text-xs font-medium text-slate-500">Format</legend>
            <div class="mt-1 grid grid-cols-2 gap-2">
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-2 py-1.5 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                    <input type="radio" name="format" value="svg" checked class="text-indigo-600"> SVG <span class="text-[11px] text-slate-500">vectoriel</span>
                </label>
                <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-slate-200 px-2 py-1.5 has-[:checked]:border-indigo-500 has-[:checked]:bg-indigo-50">
                    <input type="radio" name="format" value="png" class="text-indigo-600"> PNG
                </label>
            </div>
        </fieldset>
        <label class="block">
            <span class="text-xs font-medium text-slate-500">Taille des lots</span>
            <select name="batch_size" class="mt-1 block w-full rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                @foreach (\App\Services\QrCodeExporter::BATCH_SIZES as $size)
                    <option value="{{ $size }}" @selected($size === 500)>{{ $size ? $size.' QR codes par fichier' : 'Un seul fichier' }}</option>
                @endforeach
            </select>
        </label>
        <button class="w-full rounded-lg bg-indigo-600 px-3 py-2 font-semibold text-white hover:bg-indigo-700">Continuer</button>
        <p class="text-[11px] leading-snug text-slate-500">Chaque lot est généré au moment du téléchargement : aucun fichier n'est conservé sur le serveur.</p>
    </form>
</div>

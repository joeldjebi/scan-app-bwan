@php
    use App\Enums\PassStatus;
    use App\Services\VehicleRegistration;

    $theme = $event->theme();
    $currentBrand = old('brand', '');
    $currentColor = old('color', '');
    $presetColor = array_key_exists($currentColor, $colors) ? $currentColor : ($currentColor !== '' ? 'Autre' : '');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    @include('partials.head', ['title' => $event->name.' · Pass '.$pass->type->name])
    <meta name="theme-color" content="{{ $theme['primary_dark'] }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@700&display=swap" rel="stylesheet">
    @if ($event->posterUrl())
        <meta property="og:image" content="{{ $event->posterUrl() }}">
    @endif
    <style>
        :root {
            --brand: {{ $theme['primary'] }};
            --brand-2: {{ $theme['secondary'] }};
            --on-brand: {{ $theme['on_primary'] }};
            --brand-soft: {{ $theme['primary_soft'] }};
            --brand-dark: {{ $theme['primary_dark'] }};
        }
        body { font-family: 'Plus Jakarta Sans', ui-sans-serif, system-ui, sans-serif; background: var(--brand-dark); }
        .font-plate { font-family: 'JetBrains Mono', ui-monospace, monospace; }

        .backdrop-poster { position: fixed; inset: -40px; background-size: cover; background-position: center; filter: blur(40px) saturate(1.3); transform: scale(1.1); opacity: .55; }
        .backdrop-mesh {
            position: fixed; inset: 0;
            background:
                radial-gradient(60% 50% at 15% 10%, color-mix(in srgb, var(--brand) 85%, transparent), transparent 70%),
                radial-gradient(50% 45% at 90% 20%, color-mix(in srgb, var(--brand-2) 80%, transparent), transparent 70%),
                radial-gradient(70% 60% at 50% 100%, color-mix(in srgb, var(--brand) 55%, black), transparent 75%),
                var(--brand-dark);
            background-size: 140% 140%;
            animation: mesh 18s ease-in-out infinite alternate;
        }
        .backdrop-shade { position: fixed; inset: 0; background: linear-gradient(180deg, color-mix(in srgb, var(--brand-dark) 35%, transparent), color-mix(in srgb, var(--brand-dark) 92%, black) 75%); }

        .glass { background: rgba(255, 255, 255, .96); box-shadow: 0 30px 60px -20px rgba(0, 0, 0, .45), 0 0 0 1px rgba(255, 255, 255, .6) inset; }
        .btn-brand { background: linear-gradient(135deg, var(--brand), var(--brand-2)); color: var(--on-brand); position: relative; overflow: hidden; }
        .btn-brand::after { content: ''; position: absolute; inset: 0; background: linear-gradient(110deg, transparent 30%, rgba(255, 255, 255, .35) 50%, transparent 70%); transform: translateX(-100%); animation: shimmer 3.2s ease-in-out infinite; }
        .ring-brand:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 4px color-mix(in srgb, var(--brand) 22%, transparent); }
        .text-brand { color: var(--brand); }
        .bg-brand-soft { background: var(--brand-soft); }

        .rise { opacity: 0; animation: rise .7s cubic-bezier(.2, .8, .2, 1) forwards; }
        .float { animation: float 7s ease-in-out infinite; }
        .ticket { --notch: 14px; -webkit-mask: radial-gradient(circle var(--notch) at 0 50%, transparent 98%, #000) left / 51% 100% no-repeat, radial-gradient(circle var(--notch) at 100% 50%, transparent 98%, #000) right / 51% 100% no-repeat; mask: radial-gradient(circle var(--notch) at 0 50%, transparent 98%, #000) left / 51% 100% no-repeat, radial-gradient(circle var(--notch) at 100% 50%, transparent 98%, #000) right / 51% 100% no-repeat; }
        .swatch[aria-checked="true"] { box-shadow: 0 0 0 3px #fff, 0 0 0 5px var(--brand); transform: scale(1.08); }
        .check-draw { stroke-dasharray: 48; stroke-dashoffset: 48; animation: draw .6s .25s ease-out forwards; }
        .pop { animation: pop .5s cubic-bezier(.2, 1.4, .4, 1) both; }

        @keyframes rise { from { opacity: 0; transform: translateY(18px); } to { opacity: 1; transform: none; } }
        @keyframes float { 0%, 100% { transform: translateY(0) rotate(-2deg); } 50% { transform: translateY(-10px) rotate(-1deg); } }
        @keyframes shimmer { 0%, 60% { transform: translateX(-100%); } 100% { transform: translateX(100%); } }
        @keyframes mesh { from { background-position: 0% 0%; } to { background-position: 100% 100%; } }
        @keyframes draw { to { stroke-dashoffset: 0; } }
        @keyframes pop { from { transform: scale(.6); opacity: 0; } to { transform: scale(1); opacity: 1; } }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .01ms !important; animation-iteration-count: 1 !important; transition-duration: .01ms !important; }
            .rise { opacity: 1; }
        }
    </style>
</head>
<body class="min-h-screen text-slate-900 antialiased">
    <div class="backdrop-mesh" aria-hidden="true"></div>
    @if ($event->posterUrl())
        <div class="backdrop-poster" style="background-image: url('{{ $event->posterUrl() }}')" aria-hidden="true"></div>
    @endif
    <div class="backdrop-shade" aria-hidden="true"></div>

    <main class="relative mx-auto max-w-5xl px-3 pb-16 pt-5 min-[360px]:px-4 sm:pt-10">
        {{-- En-tête --}}
        <header class="rise flex items-center justify-between gap-3" style="animation-delay: .05s">
            <div class="flex items-center gap-3">
                @if ($event->logoUrl())
                    <span class="flex size-12 items-center justify-center rounded-2xl bg-white/95 p-1.5 shadow-lg">
                        <img src="{{ $event->logoUrl() }}" alt="{{ $event->name }}" class="max-h-full max-w-full object-contain">
                    </span>
                @endif
                <span class="hidden text-sm font-semibold uppercase tracking-[.2em] text-white/80 min-[340px]:inline">Pass parking</span>
            </div>
            <span class="rounded-full px-3 py-1 text-xs font-bold uppercase tracking-wider shadow" style="background: {{ $pass->type->color }}; color: {{ \App\Services\PosterPalette::readableTextOn($pass->type->color) }}">{{ $pass->type->name }}</span>
        </header>

        <div class="mt-6 grid min-w-0 items-start gap-6 sm:mt-8 sm:gap-8 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.15fr)] lg:gap-12">
            {{-- Événement --}}
            <section class="min-w-0 text-white">
                @if ($event->posterUrl())
                    <div class="rise mx-auto mb-8 w-48 sm:w-60 lg:mx-0 lg:w-72" style="animation-delay: .15s; perspective: 900px"
                         x-data="{ rx: 0, ry: 0 }"
                         @pointermove="const r = $el.getBoundingClientRect(); ry = (($event.clientX - r.left) / r.width - .5) * 16; rx = -(($event.clientY - r.top) / r.height - .5) * 16"
                         @pointerleave="rx = 0; ry = 0">
                        <div class="float">
                            <img src="{{ $event->posterUrl() }}" alt="Affiche — {{ $event->name }}"
                                 class="w-full rounded-2xl shadow-[0_35px_60px_-15px_rgba(0,0,0,.6)] ring-1 ring-white/20 transition-transform duration-200 ease-out"
                                 :style="`transform: rotateX(${rx}deg) rotateY(${ry}deg)`">
                        </div>
                    </div>
                @endif

                <h1 class="rise break-words text-center text-2xl font-extrabold leading-tight tracking-tight [hyphens:auto] min-[360px]:text-3xl sm:text-4xl lg:text-left lg:text-5xl" style="animation-delay: .25s">{{ $event->name }}</h1>
                <div class="rise mt-4 flex flex-wrap justify-center gap-2 text-sm lg:justify-start" style="animation-delay: .35s">
                    <span class="inline-flex max-w-full items-center gap-1.5 rounded-2xl bg-white/15 px-3 py-1.5 text-left backdrop-blur">
                        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd"/></svg>
                        {{ ucfirst($event->periodLabel()) }}
                    </span>
                    @if ($event->location)
                        <span class="inline-flex max-w-full items-center gap-1.5 rounded-2xl bg-white/15 px-3 py-1.5 text-left backdrop-blur">
                            <svg class="size-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18s6-5.33 6-10A6 6 0 0 0 4 8c0 4.67 6 10 6 10Zm0-7.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" clip-rule="evenodd"/></svg>
                            {{ $event->location }}
                        </span>
                    @endif
                </div>
                @if ($event->description)
                    <p class="rise mx-auto mt-5 max-w-md text-center text-sm leading-relaxed text-white/75 lg:mx-0 lg:text-left" style="animation-delay: .45s">{{ $event->description }}</p>
                @endif
            </section>

            {{-- Carte principale --}}
            <section class="rise min-w-0" style="animation-delay: .3s">
                <div class="glass overflow-hidden rounded-3xl">
                    {{-- Ticket --}}
                    <div class="ticket relative px-4 pb-5 pt-4 min-[360px]:px-6 min-[360px]:pb-6 min-[360px]:pt-5" style="background: linear-gradient(135deg, var(--brand), var(--brand-2)); color: var(--on-brand)">
                        <p class="text-xs font-semibold uppercase tracking-[.2em] opacity-80">Votre pass</p>
                        <div class="mt-1 flex flex-wrap items-end justify-between gap-x-4 gap-y-1">
                            {{-- Coupure uniquement après les tirets : « SINTELIGEND-VIP- » / « 0054 » --}}
                            <p class="font-plate min-w-0 break-words text-xl tracking-wider min-[360px]:text-2xl sm:text-3xl">{!! str_replace('-', '-<wbr>', e($pass->number)) !!}</p>
                            <p class="text-xs opacity-80 sm:text-right">Valable pour toutes<br>vos entrées et sorties</p>
                        </div>
                        <div class="absolute inset-x-4 bottom-0 border-t-2 border-dashed border-white/40 min-[360px]:inset-x-6"></div>
                    </div>

                    <div class="px-4 pb-6 pt-5 min-[360px]:px-6 min-[360px]:pb-7 min-[360px]:pt-6">
                        @if ($pass->status === PassStatus::Revoked)
                            <div class="flex items-start gap-3 rounded-2xl bg-red-50 p-4 text-red-800">
                                <svg class="mt-0.5 size-5 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16ZM8.28 7.22a.75.75 0 0 0-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 1 0 1.06 1.06L10 11.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L11.06 10l1.72-1.72a.75.75 0 0 0-1.06-1.06L10 8.94 8.28 7.22Z" clip-rule="evenodd"/></svg>
                                <div>
                                    <p class="font-semibold">Ce pass a été désactivé.</p>
                                    <p class="mt-1 text-sm">Veuillez contacter l'organisateur de l'événement.</p>
                                </div>
                            </div>

                        @elseif ($pass->status === PassStatus::Registered)
                            @php($vehicle = $pass->vehicle)
                            <div class="text-center">
                                <div class="pop mx-auto flex size-16 items-center justify-center rounded-full" style="background: var(--brand-soft)">
                                    <svg class="size-9 text-brand" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path class="check-draw" d="M5 12.5l4.5 4.5L19 7.5"/></svg>
                                </div>
                                <h2 class="mt-4 text-xl font-extrabold">{{ session('registered') ? 'Véhicule enregistré !' : 'Votre véhicule est enregistré' }}</h2>
                                <p class="mt-1 text-sm text-slate-500">Présentez ce même QR code à l'agent, à chaque entrée et sortie du parking.</p>
                            </div>

                            <div class="mt-6 rounded-2xl border border-slate-200 p-5">
                                <div class="mx-auto flex w-fit items-stretch overflow-hidden rounded-lg border-2 border-slate-900 bg-white shadow-sm">
                                    <span class="w-3" style="background: var(--brand)"></span>
                                    <span class="font-plate px-5 py-2 text-2xl tracking-[.15em] text-slate-900 sm:text-3xl">{{ $vehicle->plate }}</span>
                                </div>
                                <dl class="mt-5 grid grid-cols-3 gap-3 text-center text-sm">
                                    <div><dt class="text-xs text-slate-500">Marque</dt><dd class="mt-0.5 font-semibold">{{ $vehicle->brand }}</dd></div>
                                    <div>
                                        <dt class="text-xs text-slate-500">Couleur</dt>
                                        <dd class="mt-0.5 flex items-center justify-center gap-1.5 font-semibold">
                                            <span class="size-3 rounded-full ring-1 ring-slate-300" style="background: {{ $colors[$vehicle->color] ?? '#cbd5e1' }}"></span>{{ $vehicle->color }}
                                        </dd>
                                    </div>
                                    <div><dt class="text-xs text-slate-500">Téléphone</dt><dd class="mt-0.5 font-semibold">{{ $vehicle->phone }}</dd></div>
                                </dl>
                            </div>
                            <p class="mt-4 text-center text-xs text-slate-500">Une erreur ? Contactez l'organisateur pour modifier votre véhicule.</p>

                            @if (session('registered'))
                                <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.3/dist/confetti.browser.min.js"></script>
                                <script>
                                    window.addEventListener('load', () => {
                                        if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || !window.confetti) return;
                                        const colors = [@js($theme['primary']), @js($theme['secondary']), '#ffffff'];
                                        confetti({ particleCount: 120, spread: 80, origin: { y: .35 }, colors });
                                        setTimeout(() => confetti({ particleCount: 60, angle: 60, spread: 60, origin: { x: 0, y: .6 }, colors }), 250);
                                        setTimeout(() => confetti({ particleCount: 60, angle: 120, spread: 60, origin: { x: 1, y: .6 }, colors }), 400);
                                    });
                                </script>
                            @endif

                        @elseif ($event->isClosed())
                            <div class="rounded-2xl bg-slate-100 p-5 text-center text-slate-700">
                                @if ($event->hasEnded())
                                    <p class="font-semibold">Cet événement est terminé.</p>
                                    <p class="mt-1 text-sm">Les enregistrements sont fermés.</p>
                                @else
                                    <p class="font-semibold">Les enregistrements sont fermés.</p>
                                    <p class="mt-1 text-sm">L'organisateur a clôturé les enregistrements pour cet événement.</p>
                                @endif
                            </div>

                        @else
                            <form method="POST" action="{{ route('public.pass.register', $pass->token) }}" novalidate
                                  x-data="{
                                      plate: @js(old('plate', '')),
                                      brand: @js($currentBrand === VehicleRegistration::OTHER_BRAND ? old('brand_other', '') : $currentBrand),
                                      color: @js($presetColor),
                                      customColor: @js($presetColor === 'Autre' ? $currentColor : ''),
                                      phone: @js(old('phone', '')),
                                      sending: false,
                                      tried: false,
                                      get missing() {
                                          return {
                                              plate: this.plate.trim().length < 2,
                                              brand: this.brand.trim() === '',
                                              color: this.colorValue === '',
                                              phone: this.phone.replace(/\D/g, '').length < 8,
                                          };
                                      },
                                      check(event) {
                                          if (this.done === 4) { this.sending = true; return; }
                                          event.preventDefault();
                                          this.tried = true;
                                          this.$nextTick(() => [...this.$el.querySelectorAll('[data-missing]')].find((el) => el.offsetParent)?.scrollIntoView({ block: 'center', behavior: 'smooth' }));
                                      },
                                      colors: @js($colors),
                                      get colorValue() { return this.color === 'Autre' ? this.customColor.trim() : this.color; },
                                      get colorHex() { return this.colors[this.colorValue] ?? '#cbd5e1'; },
                                      get done() { return [this.plate.trim().length >= 2, this.brand.trim() !== '', this.colorValue !== '', this.phone.replace(/\D/g, '').length >= 8].filter(Boolean).length; },
                                  }"
                                  @brand-chosen="brand = $event.detail"
                                  @submit="check($event)">
                                @csrf
                                <input type="hidden" name="color" :value="colorValue">

                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <h2 class="text-lg font-extrabold">Enregistrez votre véhicule</h2>
                                        <p class="text-sm text-slate-500">Moins d'une minute. Le pass sera lié à ce véhicule.</p>
                                        <p class="mt-1 text-xs text-slate-500">Tous les champs sont obligatoires <span class="text-red-600" aria-hidden="true">*</span></p>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-bold text-brand bg-brand-soft"><span x-text="done">0</span>/4</span>
                                </div>
                                <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Progression" aria-valuemin="0" aria-valuemax="4" :aria-valuenow="done">
                                    <div class="h-full rounded-full transition-all duration-500 ease-out" :style="`width: ${done * 25}%; background: linear-gradient(90deg, var(--brand), var(--brand-2))`"></div>
                                </div>

                                @if ($errors->any())
                                    <div class="pop mt-5 rounded-2xl bg-red-50 p-4 text-sm text-red-800" role="alert">
                                        <ul class="list-disc space-y-1 pl-5">
                                            @foreach ($errors->all() as $error)
                                                <li>{{ $error }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif

                                {{-- Aperçu en direct --}}
                                <div class="mt-6 flex flex-wrap items-center gap-3 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-3 min-[360px]:gap-4 min-[360px]:p-4">
                                    <div class="flex max-w-full items-stretch overflow-hidden rounded-md border-2 border-slate-900 bg-white shadow-sm">
                                        <span class="w-2 shrink-0" style="background: var(--brand)"></span>
                                        <span class="font-plate min-w-[7rem] break-all px-3 py-1.5 text-center text-base tracking-[.12em] text-slate-900 min-[360px]:text-lg" x-text="plate.trim() ? plate.toUpperCase() : '•••• •• ••'"></span>
                                    </div>
                                    <div class="min-w-0 text-sm">
                                        <p class="truncate font-semibold" x-text="brand || 'Marque'" :class="!brand && 'text-slate-400'"></p>
                                        <p class="flex items-center gap-1.5 text-slate-500">
                                            <span class="size-3 rounded-full ring-1 ring-slate-300 transition-colors" :style="`background: ${colorHex}`"></span>
                                            <span x-text="colorValue || 'Couleur'"></span>
                                        </p>
                                    </div>
                                </div>

                                <div class="mt-6 space-y-6">
                                    {{-- 1. Immatriculation --}}
                                    <div>
                                        <label for="plate" class="flex items-center gap-2 text-sm font-semibold">
                                            <span class="flex size-6 items-center justify-center rounded-full text-xs font-bold transition" :style="plate.trim().length >= 2 ? 'background: var(--brand); color: var(--on-brand)' : 'background: #f1f5f9; color: #64748b'">1</span>
                                            <span>Immatriculation <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(obligatoire)</span></span>
                                        </label>
                                        <input id="plate" name="plate" type="text" x-model="plate" required maxlength="20" autocomplete="off" autocapitalize="characters" spellcheck="false"
                                               placeholder="Ex. 1234 AB 01"
                                               class="ring-brand font-plate mt-2 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-lg uppercase tracking-[.12em] transition min-[360px]:text-xl @error('plate') border-red-400 @enderror" :class="tried && missing.plate && '!border-red-400'" aria-required="true">
                                        <p x-show="tried && missing.plate" x-cloak data-missing class="mt-1.5 text-sm font-medium text-red-600" role="alert">L'immatriculation est obligatoire.</p>
                                        @error('plate') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    {{-- 2. Marque --}}
                                    <div>
                                        <label for="brand" class="flex items-center gap-2 text-sm font-semibold">
                                            <span class="flex size-6 items-center justify-center rounded-full text-xs font-bold transition" :style="brand.trim() ? 'background: var(--brand); color: var(--on-brand)' : 'background: #f1f5f9; color: #64748b'">2</span>
                                            <span>Marque du véhicule <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(obligatoire)</span></span>
                                        </label>
                                        <div class="mt-2">
                                            @include('partials.brand-picker', [
                                                'current' => $currentBrand,
                                                'other' => old('brand_other', ''),
                                                'inputClass' => 'ring-brand block w-full rounded-xl border border-slate-300 bg-white px-4 py-3.5 text-base transition',
                                                'otherInputClass' => 'ring-brand block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base',
                                                'optionClass' => 'flex cursor-pointer items-center justify-between rounded-lg px-3 py-2.5 text-[15px] transition-colors',
                                                'activeClass' => 'bg-brand-soft text-brand font-semibold',
                                            ])
                                        </div>
                                        <p x-show="tried && missing.brand" x-cloak data-missing class="mt-1.5 text-sm font-medium text-red-600" role="alert">Choisissez la marque du véhicule.</p>
                                        @error('brand') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                                        @error('brand_other') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    {{-- 3. Couleur --}}
                                    <div>
                                        <p id="color-label" class="flex items-center gap-2 text-sm font-semibold">
                                            <span class="flex size-6 items-center justify-center rounded-full text-xs font-bold transition" :style="colorValue ? 'background: var(--brand); color: var(--on-brand)' : 'background: #f1f5f9; color: #64748b'">3</span>
                                            <span>Couleur du véhicule <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(obligatoire)</span></span>
                                        </p>
                                        <div class="mt-3 grid grid-cols-[repeat(auto-fill,minmax(3.25rem,1fr))] gap-x-2 gap-y-3" role="radiogroup" aria-labelledby="color-label" aria-required="true">
                                            @foreach ($colors as $name => $hex)
                                                <button type="button" role="radio" :aria-checked="color === @js($name)" @click="color = @js($name)"
                                                        class="group flex flex-col items-center gap-1.5 focus:outline-none" title="{{ $name }}">
                                                    <span class="swatch size-10 rounded-full ring-1 ring-black/10 transition duration-200 group-hover:scale-110 group-focus-visible:ring-4"
                                                          :aria-checked="color === @js($name)" style="background: {{ $hex }}"></span>
                                                    <span class="text-[11px] leading-none text-slate-600" :class="color === @js($name) && 'font-bold text-slate-900'">{{ $name }}</span>
                                                </button>
                                            @endforeach
                                            <button type="button" role="radio" :aria-checked="color === 'Autre'" @click="color = 'Autre'; $nextTick(() => $refs.customColor.focus())"
                                                    class="group flex flex-col items-center gap-1.5 focus:outline-none" title="Autre couleur">
                                                <span class="swatch flex size-10 items-center justify-center rounded-full text-lg text-white ring-1 ring-black/10 transition duration-200 group-hover:scale-110"
                                                      :aria-checked="color === 'Autre'" style="background: conic-gradient(#ef4444, #f59e0b, #22c55e, #3b82f6, #a855f7, #ef4444)">+</span>
                                                <span class="text-[11px] leading-none text-slate-600" :class="color === 'Autre' && 'font-bold text-slate-900'">Autre</span>
                                            </button>
                                        </div>
                                        <input x-show="color === 'Autre'" x-cloak x-ref="customColor" type="text" x-model="customColor" maxlength="30" placeholder="Précisez la couleur"
                                               x-transition class="ring-brand mt-3 block w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-base">
                                        <p x-show="tried && missing.color" x-cloak data-missing class="mt-1.5 text-sm font-medium text-red-600" role="alert">Choisissez la couleur du véhicule.</p>
                                        @error('color') <p class="mt-1.5 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>

                                    {{-- 4. Téléphone --}}
                                    <div>
                                        <label for="phone" class="flex items-center gap-2 text-sm font-semibold">
                                            <span class="flex size-6 items-center justify-center rounded-full text-xs font-bold transition" :style="phone.replace(/\D/g, '').length >= 8 ? 'background: var(--brand); color: var(--on-brand)' : 'background: #f1f5f9; color: #64748b'">4</span>
                                            <span>Numéro de téléphone <span class="text-red-600" aria-hidden="true">*</span><span class="sr-only">(obligatoire)</span></span>
                                        </label>
                                        <div class="relative mt-2">
                                            <svg class="pointer-events-none absolute left-4 top-1/2 size-5 -translate-y-1/2 text-slate-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M2 3.5A1.5 1.5 0 0 1 3.5 2h1.15a1.5 1.5 0 0 1 1.46 1.14l.57 2.29a1.5 1.5 0 0 1-.4 1.42l-.9.9a11.04 11.04 0 0 0 5.87 5.87l.9-.9a1.5 1.5 0 0 1 1.42-.4l2.29.57A1.5 1.5 0 0 1 18 15.35v1.15a1.5 1.5 0 0 1-1.5 1.5H15C7.82 18 2 12.18 2 5V3.5Z" clip-rule="evenodd"/></svg>
                                            <input id="phone" name="phone" type="tel" x-model="phone" required inputmode="tel" autocomplete="tel"
                                                   placeholder="Ex. +225 07 00 00 00 00"
                                                   class="ring-brand block w-full rounded-xl border border-slate-300 bg-white py-3.5 pl-12 pr-4 text-base transition @error('phone') border-red-400 @enderror" :class="tried && missing.phone && '!border-red-400'" aria-required="true">
                                        </div>
                                        <p class="mt-1.5 text-xs text-slate-500">Uniquement pour vous joindre en cas de besoin le jour de l'événement.</p>
                                        <p x-show="tried && missing.phone" x-cloak data-missing class="mt-1.5 text-sm font-medium text-red-600" role="alert">Le numéro de téléphone est obligatoire (8 chiffres minimum).</p>
                                        @error('phone') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                                    </div>
                                </div>

                                <button type="submit" :disabled="sending"
                                        class="btn-brand mt-8 flex w-full items-center justify-center gap-2 rounded-2xl px-5 py-4 text-base font-bold shadow-lg transition hover:brightness-110 active:scale-[.99] disabled:opacity-70">
                                    <svg x-show="sending" x-cloak class="size-5 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="10" stroke="currentColor" stroke-opacity=".3" stroke-width="3"/><path d="M22 12a10 10 0 0 0-10-10" stroke="currentColor" stroke-width="3" stroke-linecap="round"/></svg>
                                    <span x-text="sending ? 'Enregistrement…' : 'Enregistrer mon véhicule'">Enregistrer mon véhicule</span>
                                </button>
                                <p class="mt-3 text-center text-xs text-slate-500">En validant, ce pass sera définitivement lié à ce véhicule.</p>
                            </form>
                        @endif
                    </div>
                </div>
            </section>
        </div>

        <p class="mt-12 text-center text-xs text-white/50">{{ config('app.name') }}</p>
    </main>
</body>
</html>

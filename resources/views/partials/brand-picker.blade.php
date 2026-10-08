{{--
    Sélecteur de marque avec recherche (clavier : ↑ ↓ Entrée Échap).
    Paramètres : $brands, $current, $other, $inputClass, $optionClass, $activeClass, $otherInputClass
--}}
@once
    <script>
        window.brandPicker = function (brands, current, other) {
            const OTHER = @js(\App\Services\VehicleRegistration::OTHER_BRAND);
            const normalize = (value) => value.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase().trim();

            return {
                brands, selected: current, other, query: '', open: false, active: 0,
                get filtered() {
                    const q = normalize(this.query);
                    const list = this.brands.filter((brand) => brand !== OTHER && (!q || normalize(brand).includes(q)));
                    // Les marques qui commencent par la recherche d'abord.
                    list.sort((a, b) => (normalize(b).startsWith(q) - normalize(a).startsWith(q)));
                    return [...list, OTHER];
                },
                get isOther() { return this.selected === OTHER; },
                get label() { return this.isOther ? 'Autre marque' : this.selected; },
                show() { this.open = true; this.query = ''; this.active = 0; },
                choose(brand) {
                    this.selected = brand;
                    this.open = false;
                    this.$dispatch('brand-chosen', brand === OTHER ? this.other : brand);
                    if (brand === OTHER) { this.$nextTick(() => this.$refs.other?.focus()); }
                },
                move(step) {
                    if (!this.open) { this.show(); return; }
                    this.active = (this.active + step + this.filtered.length) % this.filtered.length;
                    this.$nextTick(() => this.$refs.list?.children[this.active]?.scrollIntoView({ block: 'nearest' }));
                },
                confirm() { if (this.open && this.filtered[this.active]) { this.choose(this.filtered[this.active]); } },
            };
        };
    </script>
@endonce

<div class="relative" x-data="brandPicker(@js($brands), @js($current), @js($other))" @click.outside="open = false" @keydown.escape="open = false">
    <input type="hidden" name="brand" :value="selected">
    <div class="relative">
        <input id="brand" type="text" role="combobox" aria-autocomplete="list" :aria-expanded="open" aria-controls="brand-options"
               autocomplete="off" class="{{ $inputClass }} pr-10"
               :placeholder="selected ? label : 'Rechercher une marque…'"
               :value="open ? query : label"
               @focus="show()" @click="open || show()"
               @input="query = $event.target.value; open = true; active = 0"
               @keydown.arrow-down.prevent="move(1)" @keydown.arrow-up.prevent="move(-1)" @keydown.enter.prevent="confirm()">
        <svg class="pointer-events-none absolute right-3 top-1/2 size-5 -translate-y-1/2 opacity-50 transition" :class="open && 'rotate-180'" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M5.2 7.2a.75.75 0 0 1 1.06 0L10 10.94l3.74-3.74a.75.75 0 1 1 1.06 1.06l-4.27 4.27a.75.75 0 0 1-1.06 0L5.2 8.26a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd"/>
        </svg>
    </div>

    <ul id="brand-options" x-ref="list" x-show="open" x-cloak role="listbox"
        x-transition:enter="transition duration-150 ease-out" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
        class="absolute z-30 mt-2 max-h-64 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white p-1 text-slate-800 shadow-xl">
        <template x-for="(brand, index) in filtered" :key="brand">
            <li role="option" :aria-selected="selected === brand" @mousedown.prevent="choose(brand)" @mouseenter="active = index"
                class="{{ $optionClass }}" :class="index === active ? @js($activeClass) : ''">
                <span x-text="brand === @js(\App\Services\VehicleRegistration::OTHER_BRAND) ? 'Autre marque (à préciser)' : brand"></span>
                <svg x-show="selected === brand" class="size-4 shrink-0" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-8 8a1 1 0 0 1-1.4 0l-4-4a1 1 0 1 1 1.4-1.4L8 12.58l7.3-7.3a1 1 0 0 1 1.4 0Z" clip-rule="evenodd"/></svg>
            </li>
        </template>
        <li x-show="filtered.length === 1 && query" class="px-3 py-2 text-xs text-slate-500">Aucune marque ne correspond : choisissez « Autre marque ».</li>
    </ul>

    <input x-show="isOther" x-cloak x-ref="other" name="brand_other" type="text" maxlength="50" x-model="other"
           @input="$dispatch('brand-chosen', other)" placeholder="Précisez la marque" class="{{ $otherInputClass }} mt-2">
</div>

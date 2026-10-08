@php
    $classes = match ($status) {
        \App\Enums\PassStatus::Registered => 'bg-emerald-100 text-emerald-800',
        \App\Enums\PassStatus::Revoked => 'bg-red-100 text-red-800',
        default => 'bg-amber-100 text-amber-800',
    };
@endphp
<span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium {{ $classes }}">{{ $status->label() }}</span>

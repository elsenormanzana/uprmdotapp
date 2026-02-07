<?php

use App\Models\PermitZone;
use Livewire\Component;

new class extends Component
{
}; ?>

@php
    $zonesData = \App\Models\PermitZone::with('parkingPermitTypes')->get()->map(function ($z) {
        $types = $z->parkingPermitTypes;
        return [
            'polygon' => $z->polygon, 
            'name' => $z->name, 
            'typeName' => $types->pluck('name')->join(', ') ?: '', 
            'color' => $z->color ?? $types->first()?->color ?? 'blue'
        ];
    })->values();
@endphp

{{-- Single root element wrapper --}}
<div>
    {{-- Map fills full viewport; legend card floats on top --}}
    <div class="-m-6 -mt-6 lg:-m-8 lg:-mt-8">
        {{-- Map block: full viewport height on mobile, fixed height on desktop --}}
        <div class="relative h-[100dvh] min-h-[100vh] md:h-[420px] overflow-hidden">
            <div
                wire:ignore
                class="absolute inset-0 z-0"
                id="campus-map"
                role="application"
                aria-label="Map"
                data-zones="{{ json_encode($zonesData, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"
            ></div>

            {{-- Legend card: floating on top (desktop) / bottom (mobile), collapsible --}}
            <div 
                x-data="{ open: true }"
                class="absolute left-4 right-4 bottom-4 z-10 md:left-auto md:right-4 md:top-4 md:bottom-auto md:w-auto md:max-w-sm transition-transform duration-300 ease-in-out"
                :class="open ? 'translate-y-0' : 'translate-y-[calc(100%-3rem)] md:translate-y-0'"
            >
                <flux:card class="bg-white/95 dark:bg-zinc-900/95 backdrop-blur">
                    <div class="flex items-start justify-between gap-2 mb-2">
                        <div class="flex-1">
                            <flux:heading size="lg" class="mb-1">{{ __('Campus Map') }}</flux:heading>
                            <flux:subheading class="text-sm">{{ __('Parking types available on campus') }}</flux:subheading>
                        </div>
                        <button 
                            @click="open = !open"
                            type="button"
                            class="md:hidden p-1.5 rounded hover:bg-zinc-100 dark:hover:bg-zinc-800 transition-colors -mr-1"
                            :aria-expanded="open"
                            aria-label="{{ __('Toggle legend') }}"
                        >
                            <svg 
                                class="w-5 h-5 transition-transform duration-200"
                                :class="open ? 'rotate-180' : ''"
                                fill="none" 
                                stroke="currentColor" 
                                viewBox="0 0 24 24"
                            >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>
                    </div>
                    
                    <div 
                        x-show="open"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 transform -translate-y-2"
                        x-transition:enter-end="opacity-100 transform translate-y-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="opacity-100 transform translate-y-0"
                        x-transition:leave-end="opacity-0 transform -translate-y-2"
                        class="flex flex-wrap gap-2"
                    >
                        @foreach (\App\Models\ParkingPermitType::orderBy('name')->get() as $t)
                            <div class="flex items-center gap-2 rounded-lg border border-zinc-200 px-2 py-1.5 dark:border-zinc-700">
                                <span
                                    class="inline-block h-3 w-3 rounded-full shrink-0"
                                    style="background-color: {{ $t->color === 'white' ? '#e5e5e5' : ($t->color === 'yellow' ? '#facc15' : ($t->color === 'orange' ? '#fb923c' : ($t->color === 'green' ? '#22c55e' : ($t->color === 'violet' ? '#a855f7' : '#3b82f6')))) }};"
                                ></span>
                                <flux:text class="text-xs whitespace-nowrap">{{ $t->name }}</flux:text>
                            </div>
                        @endforeach
                    </div>
                </flux:card>
            </div>
        </div>
    </div>
</div>


<?php

use App\Models\ParkingPermitType;
use App\Models\PermitZone;
use Livewire\Component;

new class extends Component
{
    public string $name = '';

    /** @var array<int, int> */
    public array $parking_permit_type_ids = [];

    public string $polygonJson = '';

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parking_permit_type_ids' => ['required', 'array', 'min:1'],
            'parking_permit_type_ids.*' => ['integer', 'exists:parking_permit_types,id'],
            'polygonJson' => ['required', 'string'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $polygon = json_decode($this->polygonJson, true);
        if (! is_array($polygon) || empty($polygon)) {
            $this->addError('polygonJson', __('Invalid polygon. Draw on the map or paste JSON [[lat,lng], ...].'));

            return;
        }

        $zone = PermitZone::create(['name' => $this->name, 'polygon' => $polygon]);
        $zone->parkingPermitTypes()->sync(array_values(array_map('intval', $this->parking_permit_type_ids)));

        $this->reset(['name', 'parking_permit_type_ids', 'polygonJson']);
        $this->dispatch('permit-zone-created');
    }

    public function delete(int $id): void
    {
        PermitZone::findOrFail($id)->delete();
        $this->dispatch('permit-zone-deleted');
    }

    public function updateColor(int $id, string $color): void
    {
        $zone = PermitZone::findOrFail($id);
        $zone->update(['color' => $color]);
        $this->dispatch('permit-zone-color-updated');
    }
}; ?>

@php
    $zonesData = \App\Models\PermitZone::with('parkingPermitTypes')->get()->map(function ($z) {
        $types = $z->parkingPermitTypes;
        return [
            'polygon' => $z->polygon, 
            'name' => $z->name, 
            'color' => $z->color ?? $types->first()?->color ?? 'blue',
            'permitTypes' => $types->map(fn($t) => ['name' => $t->name, 'color' => $t->color])->values()->toArray()
        ];
    })->values();
@endphp

{{-- Single root element wrapper --}}
<div>
    {{-- Zones data (outside wire:ignore so Livewire can update it) --}}
    <div id="permit-zones-map-data" class="hidden" data-zones="{{ json_encode($zonesData, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}"></div>

    {{-- Map fills full viewport; create-zone card floats right; table below (scroll down to see) --}}
    <div class="-m-6 -mt-6 lg:-m-8 lg:-mt-8">
    {{-- Map block: full viewport height (dvh + vh fallback) --}}
    <div class="relative h-[100dvh] min-h-[100vh]">
        <div
            wire:ignore
            class="absolute inset-0 z-0"
            id="permit-zones-map"
            role="application"
            aria-label="Map"
            data-draw="true"
        ></div>

        {{-- Zoom hint card: floating on the bottom right --}}
        <div id="zoom-hint-card" class="absolute bottom-4 right-4 z-10 hidden md:block" style="display: none;">
            <div class="bg-white/95 dark:bg-zinc-900/95 backdrop-blur rounded-lg border border-zinc-200 dark:border-zinc-700 px-3 py-2 shadow-lg">
                <div class="flex items-center gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                    <span id="zoom-hint-key" class="inline-flex items-center justify-center w-7 h-7 rounded border border-zinc-300 dark:border-zinc-600 bg-zinc-50 dark:bg-zinc-800 font-medium text-xs text-zinc-700 dark:text-zinc-300"></span>
                    <span id="zoom-hint-text" class="text-xs">{{ __('to zoom') }}</span>
                </div>
            </div>
        </div>

        {{-- Create zone card: floating on the right --}}
        <div class="absolute right-4 top-4 z-10 left-4 md:left-auto w-full md:w-auto md:max-w-sm drop-shadow-lg">
            <flux:card class="bg-white/95 dark:bg-zinc-900/95 backdrop-blur">
                <flux:heading size="lg">{{ __('Create zone') }}</flux:heading>
                <flux:subheading class="mb-4">{{ __('Draw polygon on map, then fill details') }}</flux:subheading>
                <form wire:submit="save" class="flex flex-col gap-3">
                    <flux:input wire:model="name" :label="__('Zone name')" required />
                    <div x-data="{ selected: @entangle('parking_permit_type_ids') }">
                        <label class="flux-label">{{ __('Permit types') }}</label>
                        <flux:dropdown position="bottom" align="start" class="mt-1 w-full">
                            <flux:button type="button" variant="outline" class="w-full justify-between">
                                <span x-text="selected.length ? (selected.length === 1 ? '1 selected' : selected.length + ' selected') : '{{ __('Select permit types…') }}'"></span>
                                <flux:icon icon="chevron-down" variant="mini" class="size-4" />
                            </flux:button>
                            <flux:menu>
                                <div class="p-2 space-y-1">
                                    @foreach (ParkingPermitType::orderBy('name')->get() as $t)
                                        <label class="flex cursor-pointer items-center gap-2 rounded px-2 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-600">
                                            <input
                                                type="checkbox"
                                                value="{{ $t->id }}"
                                                class="rounded border-zinc-300 text-zinc-800 focus:ring-zinc-500 dark:border-zinc-600 dark:bg-zinc-700"
                                                :checked="selected.includes('{{ $t->id }}')"
                                                @change="$event.target.checked ? selected.push('{{ $t->id }}') : selected = selected.filter(v => v !== '{{ $t->id }}')"
                                            />
                                            <span class="text-sm font-medium text-zinc-800 dark:text-white">{{ $t->name }} ({{ $t->color }})</span>
                                        </label>
                                    @endforeach
                                </div>
                            </flux:menu>
                        </flux:dropdown>
                        @error('parking_permit_type_ids')
                            <flux:text color="red" class="mt-1 text-sm">{{ $message }}</flux:text>
                        @enderror
                    </div>
                    <div>
                        <label class="flux-label">{{ __('Polygon (draw on map or paste JSON)') }}</label>
                        <textarea
                            id="permit-zones-polygon-json"
                            wire:model="polygonJson"
                            class="flux-input mt-1 block w-full rounded-md border-zinc-300 text-sm dark:border-zinc-600 dark:bg-zinc-800"
                            rows="3"
                            placeholder='[[18.21,-67.14],[18.22,-67.13],...]'
                        ></textarea>
                        @error('polygonJson')
                            <flux:text color="red" class="mt-1 text-sm">{{ $message }}</flux:text>
                        @enderror
                    </div>
                    <flux:button type="submit" variant="primary" class="w-full">{{ __('Create zone') }}</flux:button>
                </form>
            </flux:card>
        </div>
    </div>

    {{-- Table: outside map, below the fold — scroll down to see --}}
    <section class="border-t border-zinc-200 bg-white px-6 py-8 dark:border-zinc-700 dark:bg-zinc-900 lg:px-8">
        <flux:heading size="xl" class="mb-4">{{ __('Zones') }}</flux:heading>
        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Zone') }}</flux:table.column>
                <flux:table.column>{{ __('Permit types') }}</flux:table.column>
                <flux:table.column>{{ __('Color') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach (PermitZone::with('parkingPermitTypes')->orderBy('name')->get() as $z)
                    <flux:table.row>
                        <flux:table.cell>{{ $z->name }}</flux:table.cell>
                        <flux:table.cell>{{ $z->parkingPermitTypes->pluck('name')->join(', ') ?: '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:dropdown position="bottom" align="start">
                                <flux:button type="button" variant="outline" size="sm" class="justify-start gap-2">
                                    @if($z->color)
                                        <span class="inline-block h-4 w-4 rounded-full" style="background-color: {{ $z->color === 'white' ? '#e5e5e5' : ($z->color === 'yellow' ? '#facc15' : ($z->color === 'orange' ? '#fb923c' : ($z->color === 'green' ? '#22c55e' : ($z->color === 'violet' ? '#a855f7' : '#3b82f6')))) }};"></span>
                                        <span class="capitalize">{{ $z->color }}</span>
                                    @else
                                        <span>{{ __('Select color') }}</span>
                                    @endif
                                    <flux:icon icon="chevron-down" variant="mini" class="size-4 ml-auto" />
                                </flux:button>
                                <flux:menu>
                                    <div class="p-2 space-y-1">
                                        @foreach($z->parkingPermitTypes as $pt)
                                            <button
                                                type="button"
                                                wire:click="updateColor({{ $z->id }}, '{{ $pt->color }}')"
                                                class="flex w-full cursor-pointer items-center gap-2 rounded px-2 py-1.5 hover:bg-zinc-50 dark:hover:bg-zinc-600 {{ $z->color === $pt->color ? 'bg-zinc-100 dark:bg-zinc-700' : '' }}"
                                            >
                                                <span class="inline-block h-4 w-4 rounded-full" style="background-color: {{ $pt->color === 'white' ? '#e5e5e5' : ($pt->color === 'yellow' ? '#facc15' : ($pt->color === 'orange' ? '#fb923c' : ($pt->color === 'green' ? '#22c55e' : ($pt->color === 'violet' ? '#a855f7' : '#3b82f6')))) }};"></span>
                                                <span class="text-sm font-medium text-zinc-800 dark:text-white capitalize">{{ $pt->color }}</span>
                                            </button>
                                        @endforeach
                                    </div>
                                </flux:menu>
                            </flux:dropdown>
                        </flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" variant="danger" wire:click="delete({{ $z->id }})" wire:confirm="{{ __('Delete this zone?') }}">{{ __('Delete') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
    </section>
    </div>
</div>

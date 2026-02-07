<?php

use App\Models\ParkingPermitType;
use Livewire\Component;

new class extends Component
{
    public string $name = '';
    public string $color = 'blue';
    public string $description = '';
    public ?int $editingId = null;

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'in:blue,white,yellow,orange,green,violet'],
            'description' => ['nullable', 'string'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        if ($this->editingId) {
            $type = ParkingPermitType::findOrFail($this->editingId);
            $type->update(['name' => $this->name, 'color' => $this->color, 'description' => $this->description]);
            $this->editingId = null;
            $this->dispatch('permit-type-updated');
        } else {
            ParkingPermitType::create(['name' => $this->name, 'color' => $this->color, 'description' => $this->description]);
            $this->dispatch('permit-type-created');
        }

        $this->reset(['name', 'color', 'description']);
    }

    public function edit(int $id): void
    {
        $t = ParkingPermitType::findOrFail($id);
        $this->editingId = $id;
        $this->name = $t->name;
        $this->color = $t->color;
        $this->description = $t->description ?? '';
    }

    public function cancelEdit(): void
    {
        $this->editingId = null;
        $this->reset(['name', 'color', 'description']);
    }

    public function delete(int $id): void
    {
        ParkingPermitType::findOrFail($id)->delete();
        $this->dispatch('permit-type-deleted');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('Permit Types') }}</flux:heading>
    <flux:subheading>{{ __('Create and manage parking permit types (blue, white, yellow, orange, green, violet)') }}</flux:subheading>

        <flux:card class="max-w-xl">
            <form wire:submit="save" class="flex flex-col gap-4">
                <flux:input wire:model="name" :label="__('Name')" required />
                <flux:select wire:model="color" :label="__('Color')" required>
                    @foreach (App\Models\ParkingPermitType::COLORS as $c)
                        <option value="{{ $c }}">{{ ucfirst($c) }}</option>
                    @endforeach
                </flux:select>
                <flux:input wire:model="description" :label="__('Description')" />
                <div class="flex gap-2">
                    <flux:button type="submit" variant="primary">{{ $editingId ? __('Update') : __('Create') }}</flux:button>
                    @if ($editingId)
                        <flux:button type="button" variant="ghost" wire:click="cancelEdit">{{ __('Cancel') }}</flux:button>
                    @endif
                </div>
            </form>
        </flux:card>

        <flux:table>
            <flux:table.columns>
                <flux:table.column>{{ __('Name') }}</flux:table.column>
                <flux:table.column>{{ __('Color') }}</flux:table.column>
                <flux:table.column>{{ __('Description') }}</flux:table.column>
                <flux:table.column></flux:table.column>
            </flux:table.columns>
            <flux:table.rows>
                @foreach (App\Models\ParkingPermitType::orderBy('name')->get() as $t)
                    <flux:table.row>
                        <flux:table.cell>{{ $t->name }}</flux:table.cell>
                        <flux:table.cell>
                            <span class="inline-flex h-4 w-4 rounded-full" style="background-color: {{ $t->color === 'white' ? '#e5e5e5' : ($t->color === 'yellow' ? '#facc15' : ($t->color === 'orange' ? '#fb923c' : ($t->color === 'green' ? '#22c55e' : ($t->color === 'violet' ? '#a855f7' : '#3b82f6')))) }};" title="{{ $t->color }}"></span>
                            {{ ucfirst($t->color) }}
                        </flux:table.cell>
                        <flux:table.cell>{{ $t->description ?? '—' }}</flux:table.cell>
                        <flux:table.cell>
                            <flux:button size="sm" variant="ghost" wire:click="edit({{ $t->id }})">{{ __('Edit') }}</flux:button>
                            <flux:button size="sm" variant="danger" wire:click="delete({{ $t->id }})" wire:confirm="{{ __('Delete this permit type?') }}">{{ __('Delete') }}</flux:button>
                        </flux:table.cell>
                    </flux:table.row>
                @endforeach
            </flux:table.rows>
        </flux:table>
</div>

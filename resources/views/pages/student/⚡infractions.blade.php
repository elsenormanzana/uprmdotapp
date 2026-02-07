<?php

use App\Models\Infraction;
use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function infractions()
    {
        return Infraction::where('user_id', auth()->id())
            ->with('vehicle')
            ->orderByDesc('created_at')
            ->get();
    }

    #[Computed]
    public function pendingBalance(): float
    {
        return (float) Infraction::where('user_id', auth()->id())->where('status', 'pending')->sum('amount');
    }

    public function payAll(): void
    {
        Infraction::where('user_id', auth()->id())->where('status', 'pending')->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);
        $this->dispatch('infractions-paid');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('My Infractions') }}</flux:heading>
    <flux:subheading>{{ __('View and pay your infraction balance') }}</flux:subheading>

    @if ($this->pendingBalance > 0)
        <flux:card class="max-w-xl">
            <flux:heading size="lg">{{ __('Pending balance') }}</flux:heading>
            <flux:text size="xl" class="font-semibold">${{ number_format($this->pendingBalance, 2) }}</flux:text>
            <flux:button variant="primary" class="mt-4" wire:click="payAll" wire:confirm="{{ __('Mark all pending infractions as paid?') }}">
                {{ __('Pay balance') }}
            </flux:button>
        </flux:card>
    @endif

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Date') }}</flux:table.column>
            <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
            <flux:table.column>{{ __('Amount') }}</flux:table.column>
            <flux:table.column>{{ __('Description') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @forelse ($this->infractions as $i)
                <flux:table.row>
                    <flux:table.cell>{{ $i->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                    <flux:table.cell>{{ $i->vehicle->plate }}</flux:table.cell>
                    <flux:table.cell>${{ number_format($i->amount, 2) }}</flux:table.cell>
                    <flux:table.cell>{{ $i->description }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:badge :color="$i->status === 'paid' ? 'green' : 'red'">{{ $i->status }}</flux:badge>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.row>
                    <flux:table.cell colspan="5" class="text-center text-zinc-500">{{ __('No infractions.') }}</flux:table.cell>
                </flux:table.row>
            @endforelse
        </flux:table.rows>
    </flux:table>
</div>

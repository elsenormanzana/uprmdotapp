<?php

use App\Models\Infraction;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\WithPagination;

new class extends Component
{
    use WithPagination;

    public string $search = '';
    public string $statusFilter = '';

    public function mount(): void
    {
        $this->search = (string) request()->query('search', '');
    }

    #[Computed]
    public function infractions()
    {
        $q = Infraction::query()
            ->with(['user', 'vehicle', 'issuer'])
            ->orderByDesc('created_at');

        if ($this->search) {
            $q->where(function ($query) {
                $query->whereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$this->search}%")
                    ->orWhere('email', 'like', "%{$this->search}%")
                    ->orWhere('student_id', 'like', "%{$this->search}%"))
                    ->orWhereHas('vehicle', fn ($vq) => $vq->where('plate', 'like', "%{$this->search}%"));
            });
        }

        if ($this->statusFilter === 'pending') {
            $q->where('status', 'pending');
        } elseif ($this->statusFilter === 'paid') {
            $q->where('status', 'paid');
        }

        return $q->paginate(10);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
    <flux:heading size="xl">{{ __('Search Infractions (Multas)') }}</flux:heading>
    <flux:subheading>{{ __('Search and filter vehicle infractions') }}</flux:subheading>

    <div class="flex flex-wrap gap-4">
        <flux:input wire:model.live.debounce.300ms="search" :label="__('Search')" :placeholder="__('Student, email, Student ID, plate...')" class="min-w-[200px]" />
        <flux:select wire:model.live="statusFilter" :label="__('Status')" class="min-w-[120px]">
            <option value="">{{ __('All') }}</option>
            <option value="pending">{{ __('Pending') }}</option>
            <option value="paid">{{ __('Paid') }}</option>
        </flux:select>
    </div>

    <flux:table>
    <flux:table.columns>
        <flux:table.column>{{ __('Date') }}</flux:table.column>
        <flux:table.column>{{ __('Student') }}</flux:table.column>
        <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
        <flux:table.column>{{ __('Amount') }}</flux:table.column>
        <flux:table.column>{{ __('Description') }}</flux:table.column>
        <flux:table.column>{{ __('Issued by') }}</flux:table.column>
        <flux:table.column>{{ __('Status') }}</flux:table.column>
    </flux:table.columns>
    <flux:table.rows>
        @forelse ($this->infractions as $infraction)
            <flux:table.row>
                <flux:table.cell>{{ $infraction->created_at->format('Y-m-d H:i') }}</flux:table.cell>
                <flux:table.cell>{{ $infraction->user->name }} ({{ $infraction->user->student_id ?? '—' }})</flux:table.cell>
                <flux:table.cell>{{ $infraction->vehicle->plate }}</flux:table.cell>
                <flux:table.cell>${{ number_format($infraction->amount, 2) }}</flux:table.cell>
                <flux:table.cell>{{ $infraction->description }}</flux:table.cell>
                <flux:table.cell>{{ $infraction->issuer->name ?? '—' }}</flux:table.cell>
                <flux:table.cell>
                    <flux:badge :color="$infraction->status === 'paid' ? 'green' : 'red'">
                        {{ $infraction->status }}
                    </flux:badge>
                </flux:table.cell>
            </flux:table.row>
        @empty
            <flux:table.row>
                <flux:table.cell colspan="7" class="text-center text-zinc-500">{{ __('No infractions found.') }}</flux:table.cell>
            </flux:table.row>
        @endforelse
    </flux:table.rows>
</flux:table>

    {{ $this->infractions->links() }}
</div>

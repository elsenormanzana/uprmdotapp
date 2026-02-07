<?php

use App\Models\User;
use App\Models\Vehicle;
use Livewire\Component;

new class extends Component
{
    public string $studentIdInput = '';
    public ?int $userId = null;

    public function validateStudent(): void
    {
        $this->userId = null;

        $sid = trim($this->studentIdInput);
        if (! $sid) {
            $this->addError('studentIdInput', __('Enter or scan Student ID.'));

            return;
        }

        $user = User::where('student_id', $sid)->where('role', 'student')->first();
        if (! $user) {
            $this->addError('studentIdInput', __('Student not found.'));

            return;
        }

        $this->userId = $user->id;
        $this->resetValidation('studentIdInput');
    }

    #[Computed]
    public function student(): ?User
    {
        return $this->userId ? User::with(['vehicles', 'permitAssignments.parkingPermitType'])->find($this->userId) : null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('Validate Student') }}</flux:heading>
    <flux:subheading>{{ __('Validate Student ID by scanning QR code or entering manually') }}</flux:subheading>

    <flux:card class="max-w-xl">
        <form wire:submit="validateStudent" class="flex flex-col gap-4">
            <flux:input
                wire:model="studentIdInput"
                :label="__('Student ID (scan QR or enter)')"
                placeholder="e.g. 12345678"
                autocomplete="off"
            />
            @error('studentIdInput')
                <flux:text color="red">{{ $message }}</flux:text>
            @enderror
            <flux:button type="submit" variant="primary">{{ __('Validate') }}</flux:button>
        </form>
    </flux:card>

    @if ($this->student)
        <flux:card class="max-w-xl">
            <flux:heading size="lg">{{ $this->student->name }}</flux:heading>
            <flux:text variant="subtle">{{ $this->student->email }} · {{ $this->student->student_id }}</flux:text>

            <flux:separator class="my-4" />

            <flux:heading size="md">{{ __('Vehicles') }}</flux:heading>
            @forelse ($this->student->vehicles as $v)
                <div class="mt-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text class="font-medium">{{ $v->plate }}</flux:text>
                    <flux:text variant="subtle">{{ $v->make }} {{ $v->model }} ({{ $v->year }})</flux:text>
                </div>
            @empty
                <flux:text variant="subtle">{{ __('No vehicles registered.') }}</flux:text>
            @endforelse

            <flux:heading size="md" class="mt-6">{{ __('Parking permits') }}</flux:heading>
            @forelse ($this->student->permitAssignments as $a)
                <div class="mt-2 flex items-center gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:badge color="green">{{ $a->parkingPermitType->name ?? '—' }} ({{ $a->parkingPermitType->color ?? '—' }})</flux:badge>
                    <flux:text variant="subtle">{{ $a->vehicle->plate }} · {{ $a->issued_at->format('Y-m-d') }}</flux:text>
                </div>
            @empty
                <flux:text variant="subtle">{{ __('No permits assigned.') }}</flux:text>
            @endforelse
        </flux:card>
    @endif
</div>

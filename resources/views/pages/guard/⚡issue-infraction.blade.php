<?php

use App\Models\Infraction;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Component;

new class extends Component
{
    public string $studentIdInput = '';
    public ?int $userId = null;
    public string $vehicleId = '';
    public string $amount = '';
    public string $description = '';

    public function lookupStudent(): void
    {
        $this->userId = null;
        $this->vehicleId = '';

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

    public function issue(): void
    {
        $this->validate([
            'userId' => ['required', 'exists:users,id'],
            'vehicleId' => ['required', 'exists:vehicles,id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['required', 'string', 'max:500'],
        ]);

        $vehicle = Vehicle::findOrFail((int) $this->vehicleId);
        if ($vehicle->user_id != $this->userId) {
            $this->addError('vehicleId', __('Vehicle does not belong to this student.'));

            return;
        }

        Infraction::create([
            'user_id' => $this->userId,
            'vehicle_id' => (int) $this->vehicleId,
            'amount' => (float) $this->amount,
            'description' => $this->description,
            'issued_by' => auth()->id(),
            'status' => 'pending',
        ]);

        $this->reset(['studentIdInput', 'userId', 'vehicleId', 'amount', 'description']);
        $this->dispatch('infraction-issued');
    }

    #[Computed]
    public function student(): ?User
    {
        return $this->userId ? User::with('vehicles')->find($this->userId) : null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('Issue Infraction') }}</flux:heading>
    <flux:subheading>{{ __('Validate Student ID (scan QR or enter) and issue a vehicle infraction') }}</flux:subheading>

    <flux:card class="max-w-xl">
        <form wire:submit="lookupStudent" class="flex flex-col gap-4">
            <flux:input
                wire:model="studentIdInput"
                :label="__('Student ID (scan QR or enter)')"
                placeholder="e.g. 12345678"
                autocomplete="off"
            />
            @error('studentIdInput')
                <flux:text color="red">{{ $message }}</flux:text>
            @enderror
            <flux:button type="submit" variant="primary">{{ __('Look up student') }}</flux:button>
        </form>
    </flux:card>

    @if ($this->student)
        <flux:card class="max-w-xl">
            <flux:heading size="lg">{{ $this->student->name }}</flux:heading>
            <flux:text variant="subtle">{{ $this->student->email }} · {{ $this->student->student_id }}</flux:text>

            <form wire:submit="issue" class="mt-6 flex flex-col gap-4">
                <flux:select wire:model="vehicleId" :label="__('Vehicle')" required>
                    <option value="">{{ __('Select…') }}</option>
                    @foreach ($this->student->vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->plate }} — {{ $v->make }} {{ $v->model }}</option>
                    @endforeach
                </flux:select>
                @if ($this->student->vehicles->isEmpty())
                    <flux:text color="red">{{ __('Student has no registered vehicles.') }}</flux:text>
                @endif

                <flux:input wire:model="amount" type="number" step="0.01" :label="__('Amount ($)')" required />
                <flux:input wire:model="description" :label="__('Description')" required placeholder="e.g. No permit" />

                @error('vehicleId')
                    <flux:text color="red">{{ $message }}</flux:text>
                @enderror

                <flux:button type="submit" variant="danger">{{ __('Issue infraction') }}</flux:button>
            </form>
        </flux:card>
    @endif
</div>

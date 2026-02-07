<?php

use App\Models\ParkingPermitType;
use App\Models\PermitAssignment;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Component;

new class extends Component
{
    public string $studentIdInput = '';
    public ?int $userId = null;
    public string $vehicleId = '';
    public string $parkingPermitTypeId = '';
    public ?int $expiresInDays = 365;

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

    public function assign(): void
    {
        $this->validate([
            'userId' => ['required', 'exists:users,id'],
            'vehicleId' => ['required', 'exists:vehicles,id'],
            'parkingPermitTypeId' => ['required', 'exists:parking_permit_types,id'],
            'expiresInDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
        ]);

        $vehicle = Vehicle::findOrFail((int) $this->vehicleId);
        if ($vehicle->user_id != $this->userId) {
            $this->addError('vehicleId', __('Vehicle does not belong to this student.'));

            return;
        }

        $expiresAt = $this->expiresInDays ? now()->addDays($this->expiresInDays) : null;
        PermitAssignment::create([
            'user_id' => $this->userId,
            'vehicle_id' => (int) $this->vehicleId,
            'parking_permit_type_id' => (int) $this->parkingPermitTypeId,
            'issued_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        $this->reset(['vehicleId', 'parkingPermitTypeId', 'studentIdInput', 'userId']);
        $this->dispatch('permit-assigned');
    }

    #[Computed]
    public function student(): ?User
    {
        return $this->userId ? User::with('vehicles')->find($this->userId) : null;
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('Assign Permit') }}</flux:heading>
    <flux:subheading>{{ __('Validate Student ID (scan QR or enter) and assign a parking permit') }}</flux:subheading>

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

            <form wire:submit="assign" class="mt-6 flex flex-col gap-4">
                <flux:select wire:model="vehicleId" :label="__('Vehicle')" required>
                    <option value="">{{ __('Select…') }}</option>
                    @foreach ($this->student->vehicles as $v)
                        <option value="{{ $v->id }}">{{ $v->plate }} — {{ $v->make }} {{ $v->model }} ({{ $v->year }})</option>
                    @endforeach
                </flux:select>
                @if ($this->student->vehicles->isEmpty())
                    <flux:text color="red">{{ __('Student has no registered vehicles.') }}</flux:text>
                @endif

                <flux:select wire:model="parkingPermitTypeId" :label="__('Permit type')" required>
                    <option value="">{{ __('Select…') }}</option>
                    @foreach (ParkingPermitType::orderBy('name')->get() as $t)
                        <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->color }})</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="expiresInDays" type="number" :label="__('Expires in (days)')" min="1" max="3650" placeholder="365" />

                @error('vehicleId')
                    <flux:text color="red">{{ $message }}</flux:text>
                @enderror

                <flux:button type="submit" variant="primary">{{ __('Assign permit') }}</flux:button>
            </form>
        </flux:card>
    @endif
</div>

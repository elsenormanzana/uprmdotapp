<?php

use App\Models\Infraction;
use App\Models\ParkingPermitType;
use App\Models\PermitAssignment;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Component;
use Livewire\Attributes\Computed;

new class extends Component
{
    public string $studentIdInput = '';
    public ?int $userId = null;
    public string $vehicleId = '';
    public string $parkingPermitTypeId = '';
    public ?string $expiresAt = null;
    protected int $nearExpiryDays = 30;

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
            'expiresAt' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        if (Infraction::where('user_id', $this->userId)->where('status', 'pending')->exists()) {
            $this->addError('userId', __('Student has unpaid infractions.'));

            return;
        }

        $vehicle = Vehicle::findOrFail((int) $this->vehicleId);
        if ($vehicle->user_id != $this->userId) {
            $this->addError('vehicleId', __('Vehicle does not belong to this student.'));

            return;
        }

        $activePermit = $this->activePermitForVehicle($vehicle);
        if ($activePermit && ! $this->isPermitNearExpiry($activePermit)) {
            $this->addError('vehicleId', __('Vehicle already has an active permit.'));

            return;
        }

        $expiresAt = $this->expiresAt ? \Carbon\Carbon::parse($this->expiresAt)->endOfDay() : null;
        PermitAssignment::create([
            'user_id' => $this->userId,
            'vehicle_id' => (int) $this->vehicleId,
            'parking_permit_type_id' => (int) $this->parkingPermitTypeId,
            'issued_at' => now(),
            'expires_at' => $expiresAt,
        ]);

        $this->reset(['vehicleId', 'parkingPermitTypeId', 'studentIdInput', 'userId', 'expiresAt']);
        $this->dispatch('permit-assigned');
    }

    #[Computed]
    public function student(): ?User
    {
        return $this->userId
            ? User::with([
                'vehicles.permitAssignments.parkingPermitType',
                'permitAssignments.vehicle',
                'permitAssignments.parkingPermitType',
                'infractions',
            ])->find($this->userId)
            : null;
    }

    #[Computed]
    public function pendingInfractionsCount(): int
    {
        return $this->student
            ? $this->student->infractions->where('status', 'pending')->count()
            : 0;
    }

    public function activePermitForVehicle(Vehicle $vehicle): ?PermitAssignment
    {
        $now = now();

        return $vehicle->permitAssignments
            ->filter(fn (PermitAssignment $a) => is_null($a->expires_at) || $a->expires_at->gte($now))
            ->sortByDesc(fn (PermitAssignment $a) => $a->expires_at ?? $now->copy()->addYears(100))
            ->first();
    }

    public function isPermitNearExpiry(?PermitAssignment $assignment): bool
    {
        if (! $assignment || ! $assignment->expires_at) {
            return false;
        }

        return $assignment->expires_at->lte(now()->addDays($this->nearExpiryDays));
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

            <flux:separator class="my-4" />

            <flux:heading size="md">{{ __('Vehicles') }}</flux:heading>
            @forelse ($this->student->vehicles as $v)
                @php
                    $activePermit = $this->activePermitForVehicle($v);
                    $blocked = $activePermit && ! $this->isPermitNearExpiry($activePermit);
                @endphp
                <div class="mt-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <flux:text class="font-medium">{{ $v->plate }}</flux:text>
                            <flux:text variant="subtle">{{ $v->make }} {{ $v->model }} ({{ $v->year }})</flux:text>
                        </div>
                        <flux:link
                            :href="route('admin.infractions', ['search' => $v->plate])"
                            wire:navigate
                            class="text-sm"
                        >
                            {{ __('View infractions') }}
                        </flux:link>
                    </div>
                    @if ($activePermit)
                        <div class="mt-2 flex flex-wrap items-center gap-2">
                            <flux:badge color="green">{{ $activePermit->parkingPermitType->name ?? '—' }} ({{ $activePermit->parkingPermitType->color ?? '—' }})</flux:badge>
                            @if ($activePermit->expires_at)
                                <flux:text variant="subtle">{{ __('Expires on') }} {{ $activePermit->expires_at->format('Y-m-d') }}</flux:text>
                            @else
                                <flux:text variant="subtle">{{ __('No expiration date') }}</flux:text>
                            @endif
                            @if ($blocked)
                                <flux:badge color="red">{{ __('Active permit') }}</flux:badge>
                            @else
                                <flux:badge color="amber">{{ __('Near expiration') }}</flux:badge>
                            @endif
                        </div>
                    @else
                        <flux:text variant="subtle" class="mt-2">{{ __('No active permit') }}</flux:text>
                    @endif
                </div>
            @empty
                <flux:text variant="subtle">{{ __('No vehicles registered.') }}</flux:text>
            @endforelse

            <flux:heading size="md" class="mt-6">{{ __('Permit history') }}</flux:heading>
            <flux:table class="mt-2">
                <flux:table.columns>
                    <flux:table.column>{{ __('Permit') }}</flux:table.column>
                    <flux:table.column>{{ __('Vehicle') }}</flux:table.column>
                    <flux:table.column>{{ __('Issued') }}</flux:table.column>
                    <flux:table.column>{{ __('Expires') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($this->student->permitAssignments->sortByDesc('issued_at') as $a)
                        @php
                            $isActive = is_null($a->expires_at) || $a->expires_at->gte(now());
                        @endphp
                        <flux:table.row>
                            <flux:table.cell>{{ $a->parkingPermitType->name ?? '—' }} ({{ $a->parkingPermitType->color ?? '—' }})</flux:table.cell>
                            <flux:table.cell>{{ $a->vehicle->plate ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $a->issued_at?->format('Y-m-d') ?? '—' }}</flux:table.cell>
                            <flux:table.cell>{{ $a->expires_at?->format('Y-m-d') ?? __('No expiration') }}</flux:table.cell>
                            <flux:table.cell>
                                <flux:badge :color="$isActive ? 'green' : 'zinc'">
                                    {{ $isActive ? __('Active') : __('Expired') }}
                                </flux:badge>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="5" class="text-center text-zinc-500">{{ __('No permit history.') }}</flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            @if ($this->pendingInfractionsCount > 0)
                <flux:text color="red" class="mt-4">
                    {{ __('Student has unpaid infractions. A new permit cannot be assigned.') }}
                </flux:text>
            @endif

            <form wire:submit="assign" class="mt-6 flex flex-col gap-4">
                <flux:select wire:model="vehicleId" :label="__('Vehicle')" required>
                    <option value="">{{ __('Select…') }}</option>
                    @foreach ($this->student->vehicles as $v)
                        @php
                            $activePermit = $this->activePermitForVehicle($v);
                            $blocked = $activePermit && ! $this->isPermitNearExpiry($activePermit);
                        @endphp
                        <option value="{{ $v->id }}" {{ $blocked ? 'disabled' : '' }}>
                            {{ $v->plate }} — {{ $v->make }} {{ $v->model }} ({{ $v->year }})
                            @if ($activePermit)
                                · {{ __('Active permit') }}
                            @endif
                        </option>
                    @endforeach
                </flux:select>
                @if ($this->student->vehicles->isEmpty())
                    <flux:text color="red">{{ __('Student has no registered vehicles.') }}</flux:text>
                @endif
                @if ($this->vehicleId && $this->student->vehicles->isNotEmpty())
                    @php
                        $selectedVehicle = $this->student->vehicles->firstWhere('id', (int) $this->vehicleId);
                        $selectedPermit = $selectedVehicle ? $this->activePermitForVehicle($selectedVehicle) : null;
                    @endphp
                    @if ($selectedPermit && ! $this->isPermitNearExpiry($selectedPermit))
                        <flux:text color="red">{{ __('Selected vehicle already has an active permit.') }}</flux:text>
                    @endif
                @endif

                <flux:select wire:model="parkingPermitTypeId" :label="__('Permit type')" required>
                    <option value="">{{ __('Select…') }}</option>
                    @foreach (ParkingPermitType::orderBy('name')->get() as $t)
                        <option value="{{ $t->id }}">{{ $t->name }} ({{ $t->color }})</option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="expiresAt" type="date" :label="__('Expires on')" />

                @error('vehicleId')
                    <flux:text color="red">{{ $message }}</flux:text>
                @enderror
                @error('userId')
                    <flux:text color="red">{{ $message }}</flux:text>
                @enderror

                <flux:button type="submit" variant="primary" :disabled="$this->pendingInfractionsCount > 0">
                    {{ __('Assign permit') }}
                </flux:button>
            </form>
        </flux:card>
    @endif
</div>

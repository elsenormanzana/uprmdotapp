<?php

use App\Models\Infraction;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Component;
use Livewire\Attributes\Computed;

new class extends Component
{
    public string $studentIdInput = '';
    public string $plateInput = '';
    public ?int $userId = null;
    public string $vehicleId = '';
    public string $amount = '';
    public string $description = '';

    private function normalizePlate(string $plate): string
    {
        $plate = strtoupper(trim($plate));

        return preg_replace('/[^A-Z0-9]/', '', $plate) ?? '';
    }

    public function lookupPlate(): void
    {
        $this->userId = null;
        $this->vehicleId = '';

        $plateInput = trim($this->plateInput);
        if (! $plateInput) {
            $this->addError('plateInput', __('Enter or scan the license plate.'));

            return;
        }

        $normalizedPlate = $this->normalizePlate($plateInput);
        if (! $normalizedPlate) {
            $this->addError('plateInput', __('Enter or scan the license plate.'));

            return;
        }

        $this->plateInput = strtoupper($plateInput);

        $vehicle = Vehicle::query()
            ->whereRaw("REPLACE(REPLACE(UPPER(plate), '-', ''), ' ', '') = ?", [$normalizedPlate])
            ->whereHas('user', fn ($q) => $q->where('role', 'student'))
            ->with('user')
            ->first();

        if (! $vehicle) {
            $this->addError('plateInput', __('Vehicle not found.'));

            return;
        }

        $this->userId = $vehicle->user_id;
        $this->vehicleId = (string) $vehicle->id;
        if ($vehicle->user && $vehicle->user->student_id) {
            $this->studentIdInput = $vehicle->user->student_id;
        }
        $this->resetValidation('plateInput');
    }

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

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-2xl px-3 pb-8 pt-4 sm:gap-8 sm:px-6 sm:pt-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Issue Vehicle Infraction') }}</flux:heading>
        <flux:subheading>{{ __('Find a vehicle by license plate or by student ID, then issue a vehicle infraction') }}</flux:subheading>
    </div>

    <flux:card class="w-full max-w-2xl rounded-3xl border border-zinc-200/70 bg-white/90 p-4 shadow-sm ring-1 ring-black/5 backdrop-blur dark:border-zinc-700/70 dark:bg-zinc-900/60 dark:ring-white/10 sm:mx-auto sm:p-6">
        <form wire:submit="lookupPlate" class="flex flex-col gap-5">
            <flux:input
                wire:model="plateInput"
                :label="__('License plate (scan or enter)')"
                placeholder="e.g. ABC-123"
                autocomplete="off"
            />
            @php
                $plateScannerOptions = [
                    'strings' => [
                        'secureContext' => __('Camera access requires HTTPS or localhost.'),
                        'pdf417Unsupported' => __('This browser cannot read PDF417 barcodes. Try Chrome on Android or a desktop browser that supports PDF417.'),
                        'permissionBlocked' => __('Camera permission was blocked. Check browser site settings.'),
                        'noCamera' => __('No camera found on this device.'),
                        'cameraInUse' => __('Camera is already in use by another app.'),
                        'cameraInterrupted' => __('Camera start was interrupted. Try again.'),
                        'cameraFailed' => __('Camera access failed. Try again or use manual entry.'),
                        'scanFailed' => __('Unable to read a plate code. Try again.'),
                    ],
                ];
            @endphp
            <div
                x-data="window.plateScanner({{ \Illuminate\Support\Js::from($plateScannerOptions) }})"
                x-init="init()"
                class="flex flex-col gap-3"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <flux:button
                        type="button"
                        variant="outline"
                        class="min-h-11 w-full text-base sm:w-auto"
                        x-on:click="start()"
                        x-bind:disabled="!supported || scanning"
                    >
                        {{ __('Scan plate with camera') }}
                    </flux:button>
                    <flux:button
                        type="button"
                        variant="ghost"
                        class="min-h-11 w-full text-base sm:w-auto"
                        x-show="scanning"
                        x-on:click="stop()"
                    >
                        {{ __('Stop scanning') }}
                    </flux:button>
                </div>
                <flux:text variant="subtle" x-show="!supported">
                    {{ __('Plate scanning is not supported on this device. Use manual entry instead.') }}
                </flux:text>
                <flux:text variant="subtle" x-show="permission === 'denied'">
                    {{ __('Camera permission is currently blocked for this site.') }}
                </flux:text>
                <flux:text variant="subtle" x-show="!secureContext">
                    {{ __('Camera access requires HTTPS or localhost. Open this page over HTTPS on mobile.') }}
                </flux:text>
                <flux:text variant="subtle" x-show="supported && devicesChecked && permission === 'granted' && cameras.length === 0">
                    {{ __('No camera devices detected.') }}
                </flux:text>
                <flux:text variant="subtle" x-show="cameraLabel">
                    {{ __('Using camera:') }} <span x-text="cameraLabel"></span>
                </flux:text>
                <flux:text color="red" x-show="error" x-text="error"></flux:text>
                <div
                    x-show="scanning"
                    class="relative aspect-[3/4] overflow-hidden rounded-3xl border border-zinc-200 bg-transparent shadow-sm dark:border-zinc-700 sm:aspect-[9/16]"
                >
                    <video x-ref="video" class="absolute inset-0 h-full w-full object-cover bg-transparent" autoplay muted playsinline></video>
                    <div
                        x-ref="plateOverlay"
                        class="pointer-events-none absolute border-2 border-white/80 shadow-[0_0_0_9999px_rgba(0,0,0,0.25)]"
                        style="left: 15%; top: 37.5%; width: 70%; height: 25%; border-radius: 0.75rem;"
                    ></div>
                    <canvas x-ref="canvas" class="hidden"></canvas>
                </div>
            </div>
            @error('plateInput')
                <flux:text color="red">{{ $message }}</flux:text>
            @enderror
            <flux:button type="submit" variant="primary" class="min-h-11 w-full text-base sm:w-auto">{{ __('Find vehicle') }}</flux:button>
        </form>
    </flux:card>

    <flux:card class="w-full max-w-2xl rounded-3xl border border-zinc-200/70 bg-white/90 p-4 shadow-sm ring-1 ring-black/5 backdrop-blur dark:border-zinc-700/70 dark:bg-zinc-900/60 dark:ring-white/10 sm:mx-auto sm:p-6">
        <form wire:submit="lookupStudent" class="flex flex-col gap-5">
            <flux:input
                wire:model="studentIdInput"
                :label="__('Student ID (scan QR or enter)')"
                placeholder="e.g. 12345678"
                autocomplete="off"
            />
            @error('studentIdInput')
                <flux:text color="red">{{ $message }}</flux:text>
            @enderror
            <flux:button type="submit" variant="primary" class="min-h-11 w-full text-base sm:w-auto">{{ __('Find vehicles by student ID') }}</flux:button>
        </form>
    </flux:card>

    @if ($this->student)
        <flux:card class="w-full max-w-2xl rounded-3xl border border-zinc-200/70 bg-white/90 p-4 shadow-sm ring-1 ring-black/5 backdrop-blur dark:border-zinc-700/70 dark:bg-zinc-900/60 dark:ring-white/10 sm:mx-auto sm:p-6">
            <flux:heading size="lg">{{ $this->student->name }}</flux:heading>
            <flux:text variant="subtle">{{ $this->student->email }} · {{ $this->student->student_id }}</flux:text>

            <form wire:submit="issue" class="mt-6 flex flex-col gap-5">
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

                <flux:button type="submit" variant="danger" class="min-h-11 w-full text-base sm:w-auto">{{ __('Issue vehicle infraction') }}</flux:button>
            </form>
        </flux:card>
    @endif
</div>

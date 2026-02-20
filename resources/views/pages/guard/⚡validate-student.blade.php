<?php

use App\Models\User;
use App\Models\Vehicle;
use Livewire\Component;
use Livewire\Attributes\Computed;

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

<div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl px-4 pb-6 sm:gap-6 sm:px-6">
    <div class="flex flex-col gap-2">
        <flux:heading size="xl">{{ __('Validate Student') }}</flux:heading>
        <flux:subheading>{{ __('Validate Student ID by scanning QR code or entering manually') }}</flux:subheading>
    </div>

    <flux:card class="w-full max-w-xl">
        <form wire:submit="validateStudent" class="flex flex-col gap-3 sm:gap-4">
            <flux:input
                wire:model="studentIdInput"
                :label="__('Student ID (scan QR or enter)')"
                placeholder="e.g. 12345678"
                autocomplete="off"
            />
            <div
                x-data="{
                    supported: 'BarcodeDetector' in window,
                    scanning: false,
                    error: '',
                    stream: null,
                    detector: null,
                    permission: null,
                    cameras: [],
                    cameraLabel: '',
                    selectedDeviceId: '',
                    devicesChecked: false,
                    secureContext: window.isSecureContext === true,
                    init() {
                        if (this.supported) {
                            this.detector = new BarcodeDetector({ formats: ['qr_code'] });
                        }

                        this._onNavigate = () => this.stop();
                        document.addEventListener('livewire:navigating', this._onNavigate);

                        if (navigator.permissions && navigator.permissions.query) {
                            navigator.permissions.query({ name: 'camera' }).then((status) => {
                                this.permission = status.state;
                                status.onchange = () => {
                                    this.permission = status.state;
                                    if (status.state === 'granted') {
                                        this.refreshDevices();
                                    }
                                };
                                if (status.state === 'granted') {
                                    this.refreshDevices();
                                }
                            }).catch(() => {});
                        }
                    },
                    async refreshDevices() {
                        if (!navigator.mediaDevices || !navigator.mediaDevices.enumerateDevices) return;
                        try {
                            const devices = await navigator.mediaDevices.enumerateDevices();
                            this.cameras = devices.filter((d) => d.kind === 'videoinput');
                            if (!this.selectedDeviceId && this.cameras.length > 0) {
                                const preferred = this.cameras.find((d) => /back|rear|environment/i.test(d.label));
                                this.selectedDeviceId = (preferred || this.cameras[0]).deviceId || '';
                            }
                            this.devicesChecked = true;
                        } catch (e) {
                            this.devicesChecked = true;
                        }
                    },
                    async start() {
                        this.error = '';
                        if (!this.supported || this.scanning) return;
                        if (!this.secureContext) {
                            this.error = '{{ __('Camera access requires HTTPS or localhost.') }}';
                            return;
                        }

                        try {
                            try {
                                const baseConstraints = this.selectedDeviceId
                                    ? { deviceId: { exact: this.selectedDeviceId } }
                                    : { facingMode: 'environment' };
                                this.stream = await navigator.mediaDevices.getUserMedia({
                                    video: baseConstraints,
                                    audio: false,
                                });
                            } catch (innerError) {
                                try {
                                    this.stream = await navigator.mediaDevices.getUserMedia({
                                        video: { facingMode: 'environment' },
                                        audio: false,
                                    });
                                } catch (fallbackError) {
                                    this.stream = await navigator.mediaDevices.getUserMedia({
                                        video: true,
                                        audio: false,
                                    });
                                }
                            }

                            this.$refs.video.srcObject = this.stream;
                            const track = this.stream.getVideoTracks()[0];
                            this.cameraLabel = (track && track.label) ? track.label : '';
                            if (!this.selectedDeviceId && track && track.getSettings) {
                                const settings = track.getSettings();
                                this.selectedDeviceId = settings.deviceId || this.selectedDeviceId;
                            }
                            this.refreshDevices();
                            await this.waitForVideo();
                            this.scanning = true;
                            this.scanLoop();
                        } catch (e) {
                            if (e && e.name === 'NotAllowedError') {
                                this.error = '{{ __('Camera permission was blocked. Check browser site settings.') }}';
                            } else if (e && e.name === 'NotFoundError') {
                                this.error = '{{ __('No camera found on this device.') }}';
                            } else if (e && e.name === 'NotReadableError') {
                                this.error = '{{ __('Camera is already in use by another app.') }}';
                            } else if (e && e.name === 'AbortError') {
                                this.error = '{{ __('Camera start was interrupted. Try again.') }}';
                            } else {
                                this.error = '{{ __('Camera access failed. Try again or use manual entry.') }}';
                            }
                            if (e && e.message) {
                                this.error = this.error + ' (' + e.message + ')';
                            }
                            this.stop();
                        }
                    },
                    stop() {
                        this.scanning = false;
                        if (this.stream) {
                            this.stream.getTracks().forEach((t) => t.stop());
                            this.stream = null;
                        }
                        if (this.$refs.video) {
                            this.$refs.video.srcObject = null;
                        }
                        this.cameraLabel = '';
                    },
                    waitForVideo() {
                        return new Promise((resolve, reject) => {
                            const video = this.$refs.video;
                            if (!video) return reject(new Error('Video element missing'));

                            const timeoutId = setTimeout(() => {
                                reject(new Error('Timeout starting video source'));
                            }, 5000);

                            const cleanup = () => {
                                clearTimeout(timeoutId);
                                video.onloadedmetadata = null;
                                video.oncanplay = null;
                            };

                            video.onloadedmetadata = async () => {
                                try {
                                    await video.play();
                                    cleanup();
                                    resolve();
                                } catch (e) {
                                    cleanup();
                                    reject(e);
                                }
                            };

                            video.oncanplay = async () => {
                                try {
                                    await video.play();
                                    cleanup();
                                    resolve();
                                } catch (e) {
                                    cleanup();
                                    reject(e);
                                }
                            };
                        });
                    },
                    async scanLoop() {
                        if (!this.scanning || !this.detector) return;

                        try {
                            const codes = await this.detector.detect(this.$refs.video);
                            if (codes && codes.length > 0) {
                                const value = (codes[0].rawValue || '').trim();
                                if (value) {
                                    this.$wire.set('studentIdInput', value);
                                    this.$wire.call('validateStudent');
                                    this.stop();
                                    return;
                                }
                            }
                        } catch (e) {
                            this.error = '{{ __('Unable to read QR code. Try again.') }}';
                        }

                        requestAnimationFrame(() => this.scanLoop());
                    },
                }"
                x-init="init()"
                class="flex flex-col gap-3"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <flux:button
                        type="button"
                        variant="outline"
                        class="w-full sm:w-auto"
                        x-on:click="start()"
                        x-bind:disabled="!supported || scanning"
                    >
                        {{ __('Scan QR with camera') }}
                    </flux:button>
                    <flux:button
                        type="button"
                        variant="ghost"
                        class="w-full sm:w-auto"
                        x-show="scanning"
                        x-on:click="stop()"
                    >
                        {{ __('Stop scanning') }}
                    </flux:button>
                </div>
                <flux:text variant="subtle" x-show="!supported">
                    {{ __('QR scanning is not supported on this device. Use manual entry instead.') }}
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
                    class="overflow-hidden rounded-lg border border-zinc-200 bg-black dark:border-zinc-700"
                >
                    <video x-ref="video" class="w-full aspect-video" autoplay muted playsinline></video>
                </div>
            </div>
            @error('studentIdInput')
                <flux:text color="red">{{ $message }}</flux:text>
            @enderror
            <flux:button type="submit" variant="primary" class="w-full sm:w-auto">
                {{ __('Validate') }}
            </flux:button>
        </form>
    </flux:card>

    @if ($this->student)
        <flux:card class="w-full max-w-xl">
            <div class="flex flex-col gap-1 sm:gap-2">
                <flux:heading size="lg">{{ $this->student->name }}</flux:heading>
                <flux:text variant="subtle" class="break-words">
                    {{ $this->student->email }} · {{ $this->student->student_id }}
                </flux:text>
            </div>

            <flux:separator class="my-4" />

            <flux:heading size="md">{{ __('Vehicles') }}</flux:heading>
            @forelse ($this->student->vehicles as $v)
                <div class="mt-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <flux:text class="font-medium">{{ $v->plate }}</flux:text>
                    <flux:text variant="subtle" class="break-words">
                        {{ $v->make }} {{ $v->model }} ({{ $v->year }})
                    </flux:text>
                </div>
            @empty
                <flux:text variant="subtle">{{ __('No vehicles registered.') }}</flux:text>
            @endforelse

            <flux:heading size="md" class="mt-6">{{ __('Parking permits') }}</flux:heading>
            @forelse ($this->student->permitAssignments as $a)
                <div class="mt-2 flex flex-col gap-2 rounded-lg border border-zinc-200 p-3 dark:border-zinc-700 sm:flex-row sm:items-center">
                    <flux:badge color="green">
                        {{ $a->parkingPermitType->name ?? '—' }} ({{ $a->parkingPermitType->color ?? '—' }})
                    </flux:badge>
                    <flux:text variant="subtle" class="break-words">
                        {{ $a->vehicle->plate }} · {{ $a->issued_at->format('Y-m-d') }}
                    </flux:text>
                </div>
            @empty
                <flux:text variant="subtle">{{ __('No permits assigned.') }}</flux:text>
            @endforelse
        </flux:card>
    @endif
</div>

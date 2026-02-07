<?php

use Livewire\Attributes\Computed;
use Livewire\Component;

new class extends Component
{
    #[Computed]
    public function user()
    {
        return auth()->user();
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('My ID & QR') }}</flux:heading>
    <flux:subheading>{{ __('Student ID and QR code for validation') }}</flux:subheading>

    <flux:card class="max-w-md">
        <flux:heading size="lg">{{ $this->user->name }}</flux:heading>
        <flux:text variant="subtle">{{ $this->user->email }}</flux:text>
        <flux:heading size="md" class="mt-4">{{ __('Student ID') }}</flux:heading>
        <flux:text size="xl" class="font-mono font-semibold">{{ $this->user->student_id ?? __('—') }}</flux:text>

        @if ($this->user->student_id)
            <div class="mt-6 flex flex-col items-center gap-4">
                <flux:heading size="md">{{ __('QR Code') }}</flux:heading>
                <img
                    src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data={{ urlencode($this->user->student_id) }}"
                    alt="Student ID QR"
                    class="rounded-lg border border-zinc-200 dark:border-zinc-700"
                />
                <flux:text variant="subtle">{{ __('Show this QR to validate your Student ID') }}</flux:text>
            </div>
        @else
            <flux:text color="red" class="mt-4">{{ __('No Student ID on file. Contact support.') }}</flux:text>
        @endif
    </flux:card>
</div>

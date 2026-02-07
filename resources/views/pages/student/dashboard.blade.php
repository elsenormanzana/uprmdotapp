<x-layouts::app :title="__('Student Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <flux:heading size="xl">{{ __('Student Dashboard') }}</flux:heading>
        <flux:subheading>{{ __('Manage vehicles, permits, and infractions') }}</flux:subheading>
        <div class="grid auto-rows-min gap-4 md:grid-cols-2 lg:grid-cols-4">
            <flux:link :href="route('student.vehicles')" wire:navigate class="block rounded-xl border border-neutral-200 p-6 dark:border-neutral-700 hover:border-accent-500 dark:hover:border-accent-500">
                <flux:icon.truck class="size-8 text-accent-500" />
                <flux:heading size="lg" class="mt-2">{{ __('My Vehicles') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Register a vehicle to request a parking permit') }}</flux:text>
            </flux:link>
            <flux:link :href="route('student.infractions')" wire:navigate class="block rounded-xl border border-neutral-200 p-6 dark:border-neutral-700 hover:border-accent-500 dark:hover:border-accent-500">
                <flux:icon.currency-dollar class="size-8 text-accent-500" />
                <flux:heading size="lg" class="mt-2">{{ __('My Infractions') }}</flux:heading>
                <flux:text variant="subtle">{{ __('View and pay infraction balance') }}</flux:text>
            </flux:link>
            <flux:link :href="route('student.campus-map')" wire:navigate class="block rounded-xl border border-neutral-200 p-6 dark:border-neutral-700 hover:border-accent-500 dark:hover:border-accent-500">
                <flux:icon.map class="size-8 text-accent-500" />
                <flux:heading size="lg" class="mt-2">{{ __('Campus Map') }}</flux:heading>
                <flux:text variant="subtle">{{ __('See parking types available on campus') }}</flux:text>
            </flux:link>
            <flux:link :href="route('student.profile')" wire:navigate class="block rounded-xl border border-neutral-200 p-6 dark:border-neutral-700 hover:border-accent-500 dark:hover:border-accent-500">
                <flux:icon.identification class="size-8 text-accent-500" />
                <flux:heading size="lg" class="mt-2">{{ __('My ID & QR') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Student ID and QR code') }}</flux:text>
            </flux:link>
        </div>
    </div>
</x-layouts::app>

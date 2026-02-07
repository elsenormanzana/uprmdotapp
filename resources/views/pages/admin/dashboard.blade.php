<x-layouts::app :title="__('Admin Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <flux:heading size="xl">{{ __('Admin Dashboard') }}</flux:heading>
        <flux:subheading>{{ __('Manage infractions, permit types, zones, and assignments') }}</flux:subheading>
        <div class="grid auto-rows-min gap-4 md:grid-cols-3">
            <flux:link :href="route('admin.infractions')" wire:navigate class="block rounded-xl border border-neutral-200 p-6 dark:border-neutral-700 hover:border-accent-500 dark:hover:border-accent-500">
                <flux:icon.magnifying-glass class="size-8 text-accent-500" />
                <flux:heading size="lg" class="mt-2">{{ __('Search Infractions') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Search and filter vehicle infractions (multas)') }}</flux:text>
            </flux:link>
            <flux:link :href="route('admin.permit-types')" wire:navigate class="block rounded-xl border border-neutral-200 p-6 dark:border-neutral-700 hover:border-accent-500 dark:hover:border-accent-500">
                <flux:icon.tag class="size-8 text-accent-500" />
                <flux:heading size="lg" class="mt-2">{{ __('Permit Types') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Create and manage blue, white, yellow, orange, green, violet permits') }}</flux:text>
            </flux:link>
            <flux:link :href="route('admin.permit-zones')" wire:navigate class="block rounded-xl border border-neutral-200 p-6 dark:border-neutral-700 hover:border-accent-500 dark:hover:border-accent-500">
                <flux:icon.map-pin class="size-8 text-accent-500" />
                <flux:heading size="lg" class="mt-2">{{ __('Permit Zones') }}</flux:heading>
                <flux:text variant="subtle">{{ __('GeoSelect areas allowed for each permit type') }}</flux:text>
            </flux:link>
            <flux:link :href="route('admin.assign-permit')" wire:navigate class="block rounded-xl border border-neutral-200 p-6 dark:border-neutral-700 hover:border-accent-500 dark:hover:border-accent-500 md:col-span-2">
                <flux:icon.user-plus class="size-8 text-accent-500" />
                <flux:heading size="lg" class="mt-2">{{ __('Assign Permit') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Assign a parking permit to a student (validate Student ID via QR scan)') }}</flux:text>
            </flux:link>
        </div>
    </div>
</x-layouts::app>

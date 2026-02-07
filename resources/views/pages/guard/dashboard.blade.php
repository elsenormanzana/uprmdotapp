<x-layouts::app :title="__('Guard Dashboard')">
    <div class="flex h-full w-full flex-1 flex-col gap-4 rounded-xl">
        <flux:heading size="xl">{{ __('Guard Dashboard') }}</flux:heading>
        <flux:subheading>{{ __('Issue infractions and validate students') }}</flux:subheading>
        <div class="grid auto-rows-min gap-4 md:grid-cols-2">
            <flux:link :href="route('guard.issue-infraction')" wire:navigate class="block rounded-xl border border-neutral-200 p-6 dark:border-neutral-700 hover:border-accent-500 dark:hover:border-accent-500">
                <flux:icon.document-plus class="size-8 text-accent-500" />
                <flux:heading size="lg" class="mt-2">{{ __('Issue Infraction') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Give a vehicle infraction to a student (validate via QR scan)') }}</flux:text>
            </flux:link>
            <flux:link :href="route('guard.validate-student')" wire:navigate class="block rounded-xl border border-neutral-200 p-6 dark:border-neutral-700 hover:border-accent-500 dark:hover:border-accent-500">
                <flux:icon.qr-code class="size-8 text-accent-500" />
                <flux:heading size="lg" class="mt-2">{{ __('Validate Student') }}</flux:heading>
                <flux:text variant="subtle">{{ __('Validate Student ID by scanning QR code') }}</flux:text>
            </flux:link>
        </div>
    </div>
</x-layouts::app>

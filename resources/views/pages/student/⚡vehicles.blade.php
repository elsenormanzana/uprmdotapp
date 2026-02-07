<?php

use App\Models\Vehicle;
use Livewire\Component;
use Illuminate\Support\Facades\Http;

new class extends Component
{
    public string $plate = '';
    public string $make = '';
    public string $model = '';
    public string $year = '';
    public string $color = '';
    public ?string $vehicleImageUrl = null;

    public array $makes = [];
    public array $models = [];
    public array $years = [];
    public array $colors = [
        'White', 'Black', 'Silver', 'Gray', 'Red', 'Blue', 'Green', 'Brown', 'Beige', 
        'Gold', 'Yellow', 'Orange', 'Purple', 'Pink', 'Maroon', 'Navy', 'Tan', 'Burgundy'
    ];

    public function mount(): void
    {
        $this->loadMakes();
        $this->loadYears();
    }

    public function loadMakes(): void
    {
        try {
            $response = Http::timeout(10)->get('https://vpic.nhtsa.dot.gov/api/vehicles/GetMakesForVehicleType/car?format=json');
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['Results'])) {
                    $this->makes = collect($data['Results'])
                        ->pluck('MakeName')
                        ->unique()
                        ->sort()
                        ->values()
                        ->toArray();
                }
            }
        } catch (\Exception $e) {
            // Fallback to common makes if API fails
            $this->makes = [
                'Acura', 'Audi', 'BMW', 'Buick', 'Cadillac', 'Chevrolet', 'Chrysler', 
                'Dodge', 'Ford', 'GMC', 'Honda', 'Hyundai', 'Infiniti', 'Jeep', 'Kia', 
                'Lexus', 'Lincoln', 'Mazda', 'Mercedes-Benz', 'Mitsubishi', 'Nissan', 
                'Ram', 'Subaru', 'Toyota', 'Volkswagen', 'Volvo'
            ];
        }
    }

    public function updatedMake(): void
    {
        $this->model = ''; // Reset model when make changes
        $this->vehicleImageUrl = null;
        if (!empty($this->make)) {
            $this->loadModels();
        } else {
            $this->models = [];
        }
    }

    public function updatedYear(): void
    {
        $this->model = ''; // Reset model when year changes
        $this->vehicleImageUrl = null;
        if (!empty($this->make)) {
            $this->loadModels();
        }
    }
    
    public function updatedModel(): void
    {
        $this->loadVehicleImage();
    }

    public function updatedColor(): void
    {
        $this->loadVehicleImage();
    }

    public function loadModels(): void
    {
        if (empty($this->make)) {
            $this->models = [];
            return;
        }

        try {
            $makeEncoded = urlencode($this->make);
            
            // If year is selected, filter by make and year
            if (!empty($this->year)) {
                $yearEncoded = urlencode($this->year);
                // Use GetModelsForMakeYear endpoint
                $response = Http::timeout(10)->get("https://vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMakeYear/make/{$makeEncoded}/modelyear/{$yearEncoded}?format=json");
            } else {
                // If no year selected, get all models for the make
                $response = Http::timeout(10)->get("https://vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMake/{$makeEncoded}?format=json");
            }
            
            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['Results']) && is_array($data['Results'])) {
                    $this->models = collect($data['Results'])
                        ->pluck('Model_Name')
                        ->filter()
                        ->unique()
                        ->sort()
                        ->values()
                        ->toArray();
                } else {
                    $this->models = [];
                }
            } else {
                $this->models = [];
            }
        } catch (\Exception $e) {
            $this->models = [];
        }
    }

    public function loadYears(): void
    {
        $currentYear = (int) date('Y');
        $this->years = range($currentYear, 1900);
    }

    public function loadVehicleImage(): void
    {
        $this->vehicleImageUrl = null;

        if (!$this->make || !$this->model || !$this->year) {
            return;
        }

        $apiKey = config('services.carsxe.key');
        try {
            if ($apiKey) {
                $params = [
                    'key' => $apiKey,
                    'make' => $this->make,
                    'model' => $this->model,
                    'year' => $this->year,
                    'format' => 'json',
                ];

                if ($this->color) {
                    $params['color'] = $this->color;
                }

                $response = Http::timeout(10)->get('https://api.carsxe.com/images', $params);
                if ($response->successful()) {
                    $data = $response->json();
                    $firstImage = $data['images'][0]['link'] ?? null;
                    if (is_string($firstImage) && $firstImage !== '') {
                        $this->vehicleImageUrl = $firstImage;
                        return;
                    }
                }
            }

            $search = trim($this->year . ' ' . $this->make . ' ' . $this->model . ' car');
            $queryParams = [
                'action' => 'query',
                'generator' => 'search',
                'gsrsearch' => $search,
                'gsrnamespace' => 6,
                'gsrlimit' => 1,
                'prop' => 'imageinfo',
                'iiprop' => 'url',
                'format' => 'json',
                'origin' => '*',
            ];

            $response = Http::timeout(10)->get('https://commons.wikimedia.org/w/api.php', $queryParams);
            if (!$response->successful()) {
                return;
            }

            $data = $response->json();
            $pages = $data['query']['pages'] ?? null;
            if (!is_array($pages) || !$pages) {
                return;
            }

            $firstPage = reset($pages);
            $firstImage = $firstPage['imageinfo'][0]['url'] ?? null;
            if (is_string($firstImage) && $firstImage !== '') {
                $this->vehicleImageUrl = $firstImage;
            }
        } catch (\Exception $e) {
            $this->vehicleImageUrl = null;
        }
    }

    public function getMakeLogoUrl(string $make): ?string
    {
        // Map common vehicle manufacturers to logo URLs
        // Using a combination of services for better coverage
        $makeLower = strtolower($make);
        $makeEncoded = urlencode($make);
        
        // Try Clearbit logo API (works for many brands)
        return "https://logo.clearbit.com/{$makeEncoded}.com";
    }

    public function getModelPlaceholder(): string
    {
        if ($this->make && $this->year) {
            return __('Select model…');
        }
        return __('Select make and year first');
    }

    public function rules(): array
    {
        return [
            'plate' => ['required', 'string', 'max:20'],
            'make' => ['required', 'string', 'max:100'],
            'model' => ['required', 'string', 'max:100'],
            'year' => ['required', 'string', 'size:4'],
            'color' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $user = auth()->user();
        $exists = Vehicle::where('user_id', $user->id)->where('plate', $this->plate)->exists();
        if ($exists) {
            $this->addError('plate', __('You already have this vehicle registered.'));

            return;
        }

        Vehicle::create([
            'user_id' => $user->id,
            'plate' => $this->plate,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color ?: null,
        ]);

        $this->reset(['plate', 'make', 'model', 'year', 'color']);
        $this->vehicleImageUrl = null;
        $this->dispatch('vehicle-registered');
    }

    public function delete(int $id): void
    {
        $v = Vehicle::where('user_id', auth()->id())->findOrFail($id);
        $v->delete();
        $this->dispatch('vehicle-deleted');
    }
}; ?>

<div class="flex h-full w-full flex-1 flex-col gap-6 rounded-xl">
    <flux:heading size="xl">{{ __('My Vehicles') }}</flux:heading>
    <flux:subheading>{{ __('Register a vehicle to request a parking permit') }}</flux:subheading>

    <style>
        [data-flux-dropdown] [role="menu"] {
            width: 100% !important;
            max-width: 100% !important;
            min-width: 0 !important;
        }
        [data-flux-dropdown] {
            width: 100%;
        }
    </style>

    <flux:card class="max-w-xl">
        <form wire:submit="save" class="flex flex-col gap-4">
            <flux:input wire:model="plate" :label="__('License plate')" required />
            
            <div 
                x-data="window.vehicleMakeDropdown()"
                x-init="initFromDataset($el)"
                class="w-full"
            >
                <script type="application/json" data-makes-json>
                    {!! json_encode($this->makes, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                </script>
                <script type="application/json" data-placeholder-json>
                    {!! json_encode(__('Select make...'), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                </script>
                <label class="flux-label">{{ __('Make') }} <span class="text-red-500">*</span></label>
                <input type="hidden" wire:model.live="make" x-ref="makeHiddenInput" />
                <div class="w-full" x-ref="makeButton">
                    <flux:dropdown 
                        position="bottom" 
                        align="start" 
                        class="mt-1 w-full"
                        x-on:open="open = true; setMenuWidth()"
                        x-on:close="open = false; search = ''"
                    >
                        <flux:button 
                            type="button" 
                            variant="outline" 
                            class="w-full justify-between items-center gap-2"
                            x-bind:class="!$wire.make ? 'text-zinc-500' : ''"
                        >
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <template x-if="$wire.make">
                                    <div class="relative h-6 w-6 shrink-0">
                                        <img 
                                            x-bind:src="logoUrl($wire.make, true)"
                                            x-bind:alt="$wire.make"
                                            class="h-6 w-6 object-contain"
                                            x-on:error="$el.style.display = 'none'"
                                        />
                                    </div>
                                </template>
                                <span class="truncate" x-text="$wire.make || placeholder"></span>
                            </div>
                            <flux:icon icon="chevron-down" variant="mini" class="size-4 shrink-0" />
                        </flux:button>
                        <flux:menu class="max-h-80 overflow-hidden">
                        <div class="p-2">
                            <flux:input 
                                x-model="search" 
                                :placeholder="__('Search makes…')" 
                                class="mb-2"
                                @click.stop
                                @keydown.escape.stop="$dispatch('close')"
                                autocomplete="off"
                            />
                            <div class="max-h-64 overflow-y-auto space-y-1">
                                <template x-for="makeOption in filteredMakes" :key="makeOption">
                                    <button
                                        type="button"
                                        @click="$refs.makeHiddenInput.value = makeOption; $refs.makeHiddenInput.dispatchEvent(new Event('input', { bubbles: true })); $dispatch('close')"
                                        class="w-full text-left px-2 py-1.5 rounded hover:bg-zinc-50 dark:hover:bg-zinc-600 text-sm font-medium text-zinc-800 dark:text-white transition-colors flex items-center gap-2"
                                        x-bind:class="$wire.make === makeOption ? 'bg-zinc-100 dark:bg-zinc-700' : ''"
                                    >
                                        <div class="relative h-5 w-5 shrink-0">
                                            <img 
                                                x-bind:src="logoUrl(makeOption, false)"
                                                x-bind:alt="makeOption"
                                                class="h-5 w-5 object-contain"
                                                x-on:error="$el.style.display = 'none'"
                                            />
                                        </div>
                                        <span x-text="makeOption" class="flex-1"></span>
                                    </button>
                                </template>
                                <template x-if="filteredMakes.length === 0">
                                    <div class="px-2 py-1.5 text-sm text-zinc-500 dark:text-zinc-400">
                                        {{ __('No makes found') }}
                                    </div>
                                </template>
                            </div>
                        </div>
                    </flux:menu>
                    </flux:dropdown>
                </div>
                @error('make')
                    <flux:text color="red" class="mt-1 text-sm">{{ $message }}</flux:text>
                @enderror
            </div>

            <flux:select wire:model.live="year" :label="__('Year')" required>
                <option value="">{{ __('Select year…') }}</option>
                @foreach ($this->years as $yearOption)
                    <option value="{{ $yearOption }}">{{ $yearOption }}</option>
                @endforeach
            </flux:select>
            @error('year')
                <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
            @enderror

            @if ($this->make && $this->year)
                <flux:select wire:model="model" :label="__('Model')" required>
                    <option value="">{{ __('Select model…') }}</option>
                    @foreach ($this->models as $modelOption)
                        <option value="{{ $modelOption }}">{{ $modelOption }}</option>
                    @endforeach
                </flux:select>
                @error('model')
                    <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
                @enderror
            @endif

            <flux:select wire:model="color" :label="__('Color')">
                <option value="">{{ __('Select color…') }}</option>
                @foreach ($this->colors as $colorOption)
                    <option value="{{ $colorOption }}">{{ $colorOption }}</option>
                @endforeach
            </flux:select>
            @error('color')
                <flux:text color="red" class="text-sm">{{ $message }}</flux:text>
            @enderror

            @if ($this->make && $this->year && $this->model)
                <div class="rounded-lg border border-zinc-200 p-3 dark:border-zinc-700">
                    <div class="mb-2 text-sm font-medium text-zinc-700 dark:text-zinc-200">
                        {{ __('Selected vehicle') }}
                    </div>
                    <div class="flex items-center gap-3">
                        @if ($this->vehicleImageUrl)
                            <img
                                class="h-16 w-28 shrink-0 object-contain"
                                alt="{{ $this->make }} {{ $this->model }}"
                                src="{{ $this->vehicleImageUrl }}"
                            />
                        @else
                            <div class="h-16 w-28 shrink-0 rounded bg-zinc-100 text-xs text-zinc-500 dark:bg-zinc-800 dark:text-zinc-400 flex items-center justify-center text-center">
                                {{ __('No image found') }}
                            </div>
                        @endif
                        <div class="text-sm text-zinc-700 dark:text-zinc-200">
                            <div>{{ $this->make }} {{ $this->model }}</div>
                            <div class="text-zinc-500 dark:text-zinc-400">{{ $this->year }} · {{ $this->color ?: __('No color') }}</div>
                        </div>
                    </div>
                </div>
            @endif

            <flux:button type="submit" variant="primary">{{ __('Register vehicle') }}</flux:button>
        </form>
    </flux:card>

    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Plate') }}</flux:table.column>
            <flux:table.column>{{ __('Make / Model') }}</flux:table.column>
            <flux:table.column>{{ __('Year') }}</flux:table.column>
            <flux:table.column></flux:table.column>
        </flux:table.columns>
        <flux:table.rows>
            @foreach (auth()->user()->vehicles as $v)
                <flux:table.row>
                    <flux:table.cell>{{ $v->plate }}</flux:table.cell>
                    <flux:table.cell>{{ $v->make }} {{ $v->model }}</flux:table.cell>
                    <flux:table.cell>{{ $v->year }}</flux:table.cell>
                    <flux:table.cell>
                        <flux:button size="sm" variant="danger" wire:click="delete({{ $v->id }})" wire:confirm="{{ __('Remove this vehicle?') }}">{{ __('Remove') }}</flux:button>
                    </flux:table.cell>
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>


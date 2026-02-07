<?php

namespace Database\Seeders;

use App\Models\ParkingPermitType;
use Illuminate\Database\Seeder;

class ParkingPermitTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Blue', 'color' => 'blue', 'description' => 'Blue permit parking'],
            ['name' => 'White', 'color' => 'white', 'description' => 'White permit parking'],
            ['name' => 'Yellow', 'color' => 'yellow', 'description' => 'Yellow permit parking'],
            ['name' => 'Orange', 'color' => 'orange', 'description' => 'Orange permit parking'],
            ['name' => 'Green', 'color' => 'green', 'description' => 'Green permit parking'],
            ['name' => 'Violet', 'color' => 'violet', 'description' => 'Violet permit parking'],
        ];

        foreach ($types as $t) {
            ParkingPermitType::firstOrCreate(
                ['color' => $t['color']],
                ['name' => $t['name'], 'description' => $t['description']],
            );
        }
    }
}

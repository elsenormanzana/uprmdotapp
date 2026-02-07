<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminGuardSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@uprm.edu'],
            [
                'name' => 'Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'student_id' => null,
            ],
        );

        User::firstOrCreate(
            ['email' => 'guard@uprm.edu'],
            [
                'name' => 'Security Guard',
                'password' => Hash::make('password'),
                'role' => 'security_guard',
                'student_id' => null,
            ],
        );
    }
}

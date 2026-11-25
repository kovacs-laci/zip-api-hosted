<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

class UsersSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException('UsersSeeder can only be run outside the production environment.');
        }

        User::query()->updateOrCreate(
            ['email' => 'bruno@example.test'],
            [
                'name' => 'Bruno Test User',
                'password' => 'BrunoTest123!',
                'email_verified_at' => now(),
            ],
        );
    }
}

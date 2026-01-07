<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Создаём 10 пользователей с предсказуемыми email и паролем "password"
        for ($i = 1; $i <= 10; $i++) {
            User::factory()->create([
                'name' => 'User ' . $i,
                'email' => 'user' . $i . '@example.com',
            ]);
        }
    }
}

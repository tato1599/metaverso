<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Note: Removed the default Laravel User::factory() call because this app
     * uses a custom `Usuario` model (not the framework's `User` model), and
     * running User::factory() would fail since no User factory exists for our schema.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}

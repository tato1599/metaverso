<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // La app usa el modelo Usuario, no el User del scaffold; sembramos solo datos del dominio.
        $this->call(DemoSeeder::class);
    }
}

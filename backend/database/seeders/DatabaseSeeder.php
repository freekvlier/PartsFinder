<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application: the catalogue everywhere, the workshops with fixed tokens only locally.
     */
    public function run(): void
    {
        $this->call(PartSeeder::class);

        if (app()->environment('local')) {
            $this->call(WorkshopSeeder::class);
        }
    }
}

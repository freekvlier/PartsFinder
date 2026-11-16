<?php

namespace Database\Seeders;

use App\Models\Workshop;
use Illuminate\Database\Seeder;

/**
 * Local development workshops with fixed tokens. Create real workshops with `workshop:create`.
 */
class WorkshopSeeder extends Seeder
{
    /**
     * Seed one workshop without discount and one with 10%, each with a fixed API token.
     */
    public function run(): void
    {
        $workshops = [
            'local-token-standard' => ['name' => 'Van Dijk Trucks', 'discount_percentage' => 0],
            'local-token-discount' => ['name' => 'Jansen Truck Service', 'discount_percentage' => 10],
        ];

        foreach ($workshops as $token => $attributes) {
            Workshop::create($attributes)->tokens()->create([
                'name' => 'api',
                'token' => hash('sha256', $token),
            ]);
        }
    }
}

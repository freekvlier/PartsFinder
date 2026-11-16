<?php

namespace Database\Seeders;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Colour;
use App\Models\Part;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class PartSeeder extends Seeder
{
    /**
     * Seed the catalogue from `database/seeders/data/parts.json`; prices there are in euros and stored as cents.
     */
    public function run(): void
    {
        foreach (File::json(database_path('seeders/data/parts.json')) as $data) {
            $part = Part::create([
                'brand_id' => Brand::firstOrCreate(['name' => $data['brand']])->id,
                'category_id' => Category::firstOrCreate(['name' => $data['category']])->id,
                'name' => $data['name'],
                'oem' => $data['oem'],
                'condition' => $data['condition'],
                'price_cents' => (int) round($data['price'] * 100),
                'stock_quantity' => $data['stockQuantity'],
                'image' => $data['image'],
            ]);

            $part->colours()->attach(
                collect($data['colours'] ?? [])->map(fn (string $name) => Colour::firstOrCreate(['name' => $name])->id),
            );
        }
    }
}

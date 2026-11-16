<?php

use App\Enums\Condition;
use App\Models\Brand;
use App\Models\Part;

beforeEach(fn () => actingAsWorkshop());

test('it lists every brand by name with its part count', function () {
    Part::factory()->count(2)->for(Brand::factory()->create(['name' => 'MAN']))->create();
    Part::factory()->for(Brand::factory()->create(['name' => 'DAF']))->create();
    Brand::factory()->create(['name' => 'Volvo']);

    $this->getJson('/api/brands')
        ->assertOk()
        ->assertExactJson(['data' => [
            ['name' => 'DAF', 'count' => 1],
            ['name' => 'MAN', 'count' => 2],
            ['name' => 'Volvo', 'count' => 0],
        ]]);
});

test('counts respect the active search', function () {
    $daf = Brand::factory()->create(['name' => 'DAF']);
    $man = Brand::factory()->create(['name' => 'MAN']);
    Part::factory()->for($daf)->create(['name' => 'Brake disc']);
    Part::factory()->for($daf)->create(['name' => 'Air spring']);
    Part::factory()->for($man)->create(['name' => 'Brake pad']);

    $this->getJson('/api/brands?q=brake')
        ->assertExactJson(['data' => [
            ['name' => 'DAF', 'count' => 1],
            ['name' => 'MAN', 'count' => 1],
        ]]);
});

test('counts respect the active condition', function () {
    $daf = Brand::factory()->create(['name' => 'DAF']);
    Part::factory()->for($daf)->count(2)->create(['condition' => Condition::Used]);
    Part::factory()->for($daf)->create(['condition' => Condition::New]);

    $this->getJson('/api/brands?condition=used')
        ->assertExactJson(['data' => [['name' => 'DAF', 'count' => 2]]]);
});

test('an invalid condition is rejected with 422', function () {
    $this->getJson('/api/brands?condition=broken')->assertUnprocessable();
});

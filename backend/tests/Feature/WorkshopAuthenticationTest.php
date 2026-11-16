<?php

use App\Models\Workshop;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\WorkshopSeeder;

test('a valid bearer token authenticates the workshop', function () {
    $workshop = Workshop::factory()->create(['name' => 'Acme', 'discount_percentage' => 10]);

    $this->withToken($workshop->createToken('api')->plainTextToken)
        ->getJson('/api/me')
        ->assertOk()
        ->assertExactJson(['data' => [
            'id' => $workshop->id,
            'name' => 'Acme',
            'discountPercentage' => 10,
        ]]);
});

test('a request without a token is rejected with 401', function () {
    $this->getJson('/api/me')->assertUnauthorized();
});

test('a request without a token is rejected with 401 even when it does not ask for JSON', function () {
    $this->get('/api/me')->assertUnauthorized();
});

test('an unknown token is rejected with 401', function () {
    $this->withToken('wrong-token')->getJson('/api/me')->assertUnauthorized();
});

test('the workshop seeder creates a workshop without and one with 10% discount, with fixed tokens', function () {
    $this->seed(WorkshopSeeder::class);

    $this->withToken('local-token-standard')->getJson('/api/me')->assertOk()->assertJsonPath('data.discountPercentage', 0);

    auth()->forgetGuards(); // one app instance serves both requests in a test; production does not
    $this->withToken('local-token-discount')->getJson('/api/me')->assertOk()->assertJsonPath('data.discountPercentage', 10);
});

test('the database seeder creates the fixed-token workshops in the local environment only', function () {
    $this->seed(DatabaseSeeder::class);
    expect(Workshop::count())->toBe(0);

    app()->detectEnvironment(fn () => 'local');
    $this->seed(DatabaseSeeder::class);
    expect(Workshop::count())->toBe(2);
});

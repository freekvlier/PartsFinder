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
            'discount_percentage' => 10,
        ]]);
});

test('every endpoint rejects a request without a token with 401', function (string $method, string $url) {
    $this->json($method, $url)->assertUnauthorized();
})->with([
    'me' => ['GET', '/api/me'],
    'parts' => ['GET', '/api/parts'],
    'part' => ['GET', '/api/parts/1'],
    'brands' => ['GET', '/api/brands'],
    'create quote' => ['POST', '/api/quotes'],
    'show quote' => ['GET', '/api/quotes/1'],
    'add line' => ['POST', '/api/quotes/1/lines'],
    'update line' => ['PATCH', '/api/quotes/1/lines/1'],
    'remove line' => ['DELETE', '/api/quotes/1/lines/1'],
    'submit quote' => ['POST', '/api/quotes/1/submit'],
]);

test('a request without a token is rejected with 401 even when it does not ask for JSON', function () {
    $this->get('/api/me')->assertUnauthorized();
});

test('an unknown token is rejected with 401', function () {
    $this->withToken('wrong-token')->getJson('/api/me')->assertUnauthorized();
});

test('the workshop seeder creates a workshop without and one with 10% discount, with fixed tokens', function () {
    $this->seed(WorkshopSeeder::class);

    $this->withToken('local-token-standard')->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.discount_percentage', 0);

    auth()->forgetGuards();
    $this->withToken('local-token-discount')->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.discount_percentage', 10);
});

test('the database seeder creates the fixed-token workshops in the local environment only', function () {
    $this->seed(DatabaseSeeder::class);
    expect(Workshop::count())->toBe(0);

    app()->detectEnvironment(fn () => 'local');
    $this->seed(DatabaseSeeder::class);
    expect(Workshop::count())->toBe(2);
});

<?php

use App\Enums\Condition;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Part;

beforeEach(fn () => actingAsWorkshop());

test('it lists parts with the data the overview needs', function () {
    Part::factory()
        ->for(Brand::factory()->create(['name' => 'DAF']))
        ->for(Category::factory()->create(['name' => 'Body']))
        ->withColours(['white', 'red'])
        ->create([
            'name' => 'DAF XF 106 Sideskirt set',
            'oem' => '2119711',
            'condition' => Condition::Used,
            'price_cents' => 79500,
            'stock_quantity' => 3,
            'image' => '/img/placeholder-part.svg',
        ]);

    $this->getJson('/api/parts')
        ->assertOk()
        ->assertJsonPath('data.0.name', 'DAF XF 106 Sideskirt set')
        ->assertJsonPath('data.0.oem', '2119711')
        ->assertJsonPath('data.0.brand', 'DAF')
        ->assertJsonPath('data.0.category', 'Body')
        ->assertJsonPath('data.0.condition', 'used')
        ->assertJsonPath('data.0.price_cents', 79500)
        ->assertJsonPath('data.0.stock_quantity', 3)
        ->assertJsonPath('data.0.in_stock', true)
        ->assertJsonPath('data.0.image', '/img/placeholder-part.svg')
        ->assertJsonCount(2, 'data.0.colours');
});

test('a part without stock is not in stock', function () {
    Part::factory()->create(['stock_quantity' => 0]);

    $this->getJson('/api/parts')->assertJsonPath('data.0.in_stock', false);
});

test('a part without colours has an empty colour list', function () {
    Part::factory()->create();

    $this->getJson('/api/parts')->assertJsonPath('data.0.colours', []);
});

test('it paginates and reports the page info in meta', function () {
    Part::factory()->count(5)->sequence(fn ($sequence) => ['name' => 'Part '.$sequence->index])->create();

    $this->getJson('/api/parts?per_page=2&page=2')
        ->assertOk()
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.current_page', 2)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Part 2')
        ->assertJsonPath('data.1.name', 'Part 3')
        ->assertJsonMissing(['name' => 'Part 0'])
        ->assertJsonMissing(['name' => 'Part 4']);
});

test('the page size defaults to 12', function () {
    Part::factory()->count(13)->create();

    $this->getJson('/api/parts')
        ->assertJsonCount(12, 'data')
        ->assertJsonPath('meta.per_page', 12);
});

test('parts are ordered by name, then id, so pages stay stable', function () {
    Part::factory()->create(['name' => 'B part']);
    Part::factory()->create(['name' => 'A part']);

    $this->getJson('/api/parts')
        ->assertJsonPath('data.0.name', 'A part')
        ->assertJsonPath('data.1.name', 'B part');
});

test('an invalid page size or page is rejected with 422', function (string $query) {
    $this->getJson('/api/parts?'.$query)->assertUnprocessable();
})->with([
    'too many per page' => 'per_page=51',
    'zero per page' => 'per_page=0',
    'page zero' => 'page=0',
    'not a number' => 'page=abc',
]);

test('the detail of a part includes its colours and stock quantity', function () {
    $part = Part::factory()
        ->for(Brand::factory()->create(['name' => 'Volvo']))
        ->withColours(['white', 'blue'])
        ->create(['name' => 'Volvo FH 4 Sideskirt set', 'stock_quantity' => 7, 'price_cents' => 64000]);

    $this->getJson("/api/parts/{$part->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $part->id)
        ->assertJsonPath('data.name', 'Volvo FH 4 Sideskirt set')
        ->assertJsonPath('data.brand', 'Volvo')
        ->assertJsonPath('data.stock_quantity', 7)
        ->assertJsonPath('data.in_stock', true)
        ->assertJsonPath('data.price_cents', 64000)
        ->assertJsonCount(2, 'data.colours');
});

test('an unknown part is a 404', function () {
    $this->getJson('/api/parts/999')->assertNotFound();
});

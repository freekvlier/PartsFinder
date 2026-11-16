<?php

use App\Condition;
use App\Models\Brand;
use App\Models\Part;
use App\Models\Workshop;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    $this->withToken(Workshop::factory()->create()->createToken('api')->plainTextToken);
});

/**
 * The names of the parts in the response, in the order returned.
 *
 * @return list<string>
 */
function returnedNames(TestResponse $response): array
{
    return collect($response->json('data'))->pluck('name')->all();
}

test('search matches part names and leaves other parts out', function () {
    Part::factory()->create(['name' => 'Brake disc front', 'oem' => '1000001']);
    Part::factory()->create(['name' => 'Air spring', 'oem' => '1000002']);

    expect(returnedNames($this->getJson('/api/parts?q=brake')))->toBe(['Brake disc front']);
});

test('search matches the OEM number', function () {
    Part::factory()->create(['name' => 'Brake disc', 'oem' => '2119711']);
    Part::factory()->create(['name' => 'Air spring', 'oem' => '2108022']);

    expect(returnedNames($this->getJson('/api/parts?q=2108')))->toBe(['Air spring']);
});

test('search ignores case and surrounding spaces', function () {
    Part::factory()->create(['name' => 'Turbocharger']);
    Part::factory()->create(['name' => 'Air spring']);

    expect(returnedNames($this->getJson('/api/parts?q=%20TURBO%20')))->toBe(['Turbocharger']);
});

test('search treats percent and underscore as plain characters', function () {
    Part::factory()->create(['name' => '100% genuine']);
    Part::factory()->create(['name' => 'Plain part']);

    expect(returnedNames($this->getJson('/api/parts?q=%25')))->toBe(['100% genuine'])
        ->and(returnedNames($this->getJson('/api/parts?q=_')))->toBe([]);
});

test('a blank search returns every part', function () {
    Part::factory()->count(3)->create();

    $this->getJson('/api/parts?q=')->assertJsonPath('meta.total', 3);
});

test('the brand filter keeps only the chosen brands', function () {
    $daf = Brand::factory()->create(['name' => 'DAF']);
    $man = Brand::factory()->create(['name' => 'MAN']);
    $volvo = Brand::factory()->create(['name' => 'Volvo']);
    Part::factory()->for($daf)->create(['name' => 'DAF part']);
    Part::factory()->for($man)->create(['name' => 'MAN part']);
    Part::factory()->for($volvo)->create(['name' => 'Volvo part']);

    $names = returnedNames($this->getJson('/api/parts?brand[]=DAF&brand[]=Volvo'));

    expect($names)->toBe(['DAF part', 'Volvo part']);
});

test('an unknown brand returns no parts', function () {
    Part::factory()->count(2)->create();

    $this->getJson('/api/parts?brand[]=Nope')->assertOk()->assertJsonPath('meta.total', 0);
});

test('the condition filter keeps only parts in that condition', function () {
    Part::factory()->create(['name' => 'New part', 'condition' => Condition::New]);
    Part::factory()->create(['name' => 'Used part', 'condition' => Condition::Used]);

    expect(returnedNames($this->getJson('/api/parts?condition=used')))->toBe(['Used part'])
        ->and(returnedNames($this->getJson('/api/parts?condition=new')))->toBe(['New part']);
});

test('search, brand and condition combine', function () {
    $daf = Brand::factory()->create(['name' => 'DAF']);
    $man = Brand::factory()->create(['name' => 'MAN']);
    Part::factory()->for($daf)->create(['name' => 'Brake disc', 'condition' => Condition::Used]);
    Part::factory()->for($daf)->create(['name' => 'Brake disc new', 'condition' => Condition::New]);
    Part::factory()->for($man)->create(['name' => 'Brake disc MAN', 'condition' => Condition::Used]);
    Part::factory()->for($daf)->create(['name' => 'Air spring', 'condition' => Condition::Used]);

    $names = returnedNames($this->getJson('/api/parts?q=brake&brand[]=DAF&condition=used'));

    expect($names)->toBe(['Brake disc']);
});

test('parts sort by price in both directions', function () {
    Part::factory()->create(['name' => 'Middle', 'price_cents' => 5000]);
    Part::factory()->create(['name' => 'Cheap', 'price_cents' => 1000]);
    Part::factory()->create(['name' => 'Expensive', 'price_cents' => 9000]);

    expect(returnedNames($this->getJson('/api/parts?sort=price_asc')))->toBe(['Cheap', 'Middle', 'Expensive'])
        ->and(returnedNames($this->getJson('/api/parts?sort=price_desc')))->toBe(['Expensive', 'Middle', 'Cheap']);
});

test('parts with the same price keep a stable order by name', function () {
    Part::factory()->create(['name' => 'B part', 'price_cents' => 1000]);
    Part::factory()->create(['name' => 'A part', 'price_cents' => 1000]);

    expect(returnedNames($this->getJson('/api/parts?sort=price_desc')))->toBe(['A part', 'B part']);
});

test('filters apply before pagination, so the meta counts only matches', function () {
    Part::factory()->count(5)->create(['condition' => Condition::Used]);
    Part::factory()->count(4)->create(['condition' => Condition::New]);

    $this->getJson('/api/parts?condition=used&per_page=2&page=3')
        ->assertOk()
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonCount(1, 'data');
});

test('an invalid filter is rejected with 422', function (string $query) {
    $this->getJson('/api/parts?'.$query)->assertUnprocessable();
})->with([
    'unknown condition' => 'condition=broken',
    'unknown sort' => 'sort=name_desc',
    'brand not a list' => 'brand=DAF',
    'search too long' => 'q='.str_repeat('x', 101),
]);

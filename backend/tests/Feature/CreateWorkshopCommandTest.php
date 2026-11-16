<?php

use App\Models\Workshop;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

test('it creates a workshop and prints an API token that authenticates it', function () {
    $this->artisan('workshop:create', ['name' => 'Acme Trucks', 'discount' => 10])->assertSuccessful();

    $workshop = Workshop::firstWhere('name', 'Acme Trucks');
    expect((float) $workshop->discount_percentage)->toBe(10.0)
        ->and($workshop->tokens)->toHaveCount(1);
});

test('the printed token authenticates the new workshop', function () {
    $output = new BufferedOutput;
    Artisan::call('workshop:create', ['name' => 'Acme Trucks'], $output);

    preg_match('/(\d+\|\w+)/', $output->fetch(), $matches);

    $this->withToken($matches[1])->getJson('/api/me')->assertOk()->assertJsonPath('data.name', 'Acme Trucks');
});

test('the discount defaults to 0', function () {
    $this->artisan('workshop:create', ['name' => 'Acme Trucks'])->assertSuccessful();

    expect((float) Workshop::first()->discount_percentage)->toBe(0.0);
});

test('an invalid discount is rejected and nothing is created', function (string $discount) {
    $this->artisan('workshop:create', ['name' => 'Acme Trucks', 'discount' => $discount])->assertFailed();

    expect(Workshop::count())->toBe(0);
})->with(['not a number' => 'abc', 'negative' => '-5', 'above 100' => '101']);

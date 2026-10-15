<?php

use App\Lab\NPlusOne\NPlusOneLab;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    config(['database.seed_scale' => 'small']);
    $this->seed(DatabaseSeeder::class);
});

test('lab index renders all variants', function () {
    $response = $this->get(route('lab.n-plus-one'))->assertOk();

    foreach ((new NPlusOneLab)->variants() as $variant) {
        $response->assertSee($variant->title);
    }

    $response->assertSee('worst')->assertSee('best');
});

test('each variant detail page renders', function (string $key) {
    $this->get(route('lab.n-plus-one', ['variant' => $key]))
        ->assertOk()
        ->assertSee('Queries grouped by normalized SQL');
})->with(fn () => array_keys((new NPlusOneLab)->variants()));

test('unknown variant is a 404', function () {
    $this->get(route('lab.n-plus-one', ['variant' => 'nope']))->assertNotFound();
});

test('all lazy (a) runs more queries than the fully eager variant (d) on the same data', function () {
    $lab = new NPlusOneLab;

    $bad = $lab->profile($lab->variant('bad'));
    $eager = $lab->profile($lab->variant('with-full'));

    expect($bad->error)->toBeNull()
        ->and($eager->error)->toBeNull()
        ->and($eager->queries)->toBe(4)
        ->and($bad->queries)->toBe(1 + 4 * count($lab->bookIds()))
        ->and($bad->duplicates())->not->toBeEmpty()
        ->and($eager->duplicates())->toBeEmpty();
});

test('strict variant (b) reports the lazy loading violation', function () {
    $lab = new NPlusOneLab;

    expect($lab->profile($lab->variant('strict'))->result)
        ->toContain('LazyLoadingViolationException')
        ->toContain('Attempted to lazy load');
});

test('fixed variants produce no duplicate queries', function (string $key) {
    $lab = new NPlusOneLab;
    $profile = $lab->profile($lab->variant($key));

    expect($profile->error)->toBeNull()
        ->and($profile->duplicates())->toBeEmpty();
})->with(['with-full', 'feed-nested', 'feed-limited', 'load-missing', 'images-morph-with', 'select-columns', 'accessor-eager', 'inverse-chaperone', 'autoload']);

test('broken select (k3) leaves genre silently null', function () {
    $lab = new NPlusOneLab;

    expect($lab->profile($lab->variant('select-missing-fk'))->result)->toContain('— no genre —');
});

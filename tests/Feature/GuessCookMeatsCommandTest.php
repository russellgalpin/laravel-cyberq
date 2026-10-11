<?php

use App\Enums\Meat;
use App\Models\Cook;
use App\Models\Guru;
use App\Models\Probe;

it('guesses the meat from how a cook was described', function (string $description, ?Meat $meat) {
    expect(Meat::guessFrom($description))->toBe($meat);
})->with([
    ['Pulled Port', Meat::PulledPork],
    ['Small Pork Shoulder with Mum and Dad', Meat::PulledPork],
    ['Shoulder of lamb mum and dad', Meat::LambShoulder],
    ['Lamb Sholder', Meat::LambShoulder],
    ['Brisket - Arthur\'s Birthday', Meat::Brisket],
    ['Ribs Dads Day', Meat::PorkRibs],
    ['Beef short ribs', Meat::BeefShortRibs],
    ['Chicken thighs for tea', Meat::ChickenThighs],
    ['40th Birthday', null],
]);

it('sets the guessed meat on the food probes that have readings', function () {
    $guru = Guru::factory()->withProbes()->create();
    $pork = Cook::factory()->for($guru)->ended()->create(['name' => 'Rose\'s birthday', 'description' => 'Huge shoulder, injected']);
    simulateCook($pork, Probe::FOOD1, minutes: 30);
    simulateCook($pork, Probe::FOOD2, minutes: 30, recordPit: false);
    $mystery = Cook::factory()->for($guru)->ended()->create(['name' => '40th Birthday']);
    $alreadyKnown = Cook::factory()->for($guru)->ended()->create(['name' => 'Brisket']);
    $alreadyKnown->useProbes([Probe::FOOD1], [Probe::FOOD1 => ['meat' => Meat::Turkey]]);

    $this->artisan('cooks:guess-meats')
        ->expectsOutputToContain("Cook {$pork->id} `Rose's birthday`: Pulled pork on Food 1, Food 2.")
        ->expectsOutputToContain("Cook {$mystery->id} `40th Birthday`: no guess")
        ->expectsOutputToContain('Set the meat on 1 of 2 cooks.')
        ->assertSuccessful();

    $pork->refresh();

    expect($pork->probesInUse()->pluck('identifier')->all())->toBe([Probe::PIT, Probe::FOOD1, Probe::FOOD2])
        ->and($pork->probeMeat(Probe::FOOD2))->toBe(Meat::PulledPork)
        ->and($alreadyKnown->fresh()->probeMeat(Probe::FOOD1))->toBe(Meat::Turkey);
});

it('only shows its guesses on a dry run', function () {
    $cook = Cook::factory()->ended()->create(['name' => 'Lamb shoulder']);
    simulateCook($cook, Probe::FOOD1, minutes: 10);

    $this->artisan('cooks:guess-meats --dry-run')
        ->expectsOutputToContain('Would set the meat on 1 of 1 cooks.')
        ->assertSuccessful();

    expect($cook->fresh()->probeMeat(Probe::FOOD1))->toBeNull();
});

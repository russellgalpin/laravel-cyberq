<?php

use App\Filament\Pages\CompareCooks;
use App\Filament\Widgets\CookComparisonChart;
use App\Models\Cook;
use App\Models\Probe;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('compares the three most recent cooks by default', function () {
    $cooks = collect(range(1, 4))->map(fn (int $daysAgo) => Cook::factory()->ended()->create([
        'started_at' => now()->subDays($daysAgo * 7),
        'ended_at' => now()->subDays($daysAgo * 7)->addHours(10),
    ]));

    $this->get('/compare')->assertOk();

    Livewire::test(CompareCooks::class)->assertSet('filters', [
        'cooks' => $cooks->take(3)->pluck('id')->all(),
        'probe' => Probe::FOOD1,
    ]);
});

it('opens with the cook it was linked from', function () {
    $cook = Cook::factory()->ended()->create();

    $this->get(CompareCooks::urlFor([$cook->id], Probe::PIT))->assertOk();

    expect(CompareCooks::urlFor([$cook->id], Probe::PIT))->toContain('filters')->toContain(Probe::PIT);
});

it('lines up the chosen cooks by hours into the cook', function () {
    $brisket = Cook::factory()->ended()->create(['name' => 'Brisket', 'started_at' => now()->subDays(10)]);
    $pork = Cook::factory()->ended()->create(['name' => 'Pork', 'started_at' => now()->subDays(5)]);
    recordReadings($brisket, Probe::FOOD1, [0 => [400, null], 120 => [1500, null]]);
    recordReadings($pork, Probe::FOOD1, [30 => [600, null]]);

    $options = Livewire::test(CookComparisonChart::class, [
        'pageFilters' => ['cooks' => [$pork->id, $brisket->id], 'probe' => Probe::FOOD1],
    ])->get('options');

    expect($options['series'])->toHaveCount(2)
        ->and($options['series'][0]['name'])->toStartWith('Brisket')
        ->and($options['series'][0]['data'])->toBe([[0.0, 40.0], [2.0, 150.0]])
        ->and($options['series'][1]['data'])->toBe([[0.5, 60.0]]);
});

it('ignores a probe it does not know about', function () {
    $chart = Livewire::test(CookComparisonChart::class, ['pageFilters' => ['probe' => 'WIFI_KEY']])->instance();

    expect($chart->probe())->toBe(Probe::FOOD1);
});

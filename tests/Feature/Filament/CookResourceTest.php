<?php

use App\Filament\Resources\Cooks\Pages\CreateCook;
use App\Filament\Resources\Cooks\Pages\EditCook;
use App\Filament\Resources\Cooks\Pages\ListCooks;
use App\Filament\Resources\Cooks\Pages\ViewCook;
use App\Filament\Widgets\CookTemperatureChart;
use App\Models\Cook;
use App\Models\Guru;
use App\Models\Probe;
use App\Models\Reading;
use App\Models\User;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->freezeSecond();
});

it('lists cooks newest first', function () {
    $old = Cook::factory()->ended()->create(['name' => 'Old brisket']);
    $current = Cook::factory()->create(['name' => 'Pork shoulder']);
    $forgotten = Cook::factory()->ended()->create(['ended_automatically' => true]);

    $this->get('/cooks')->assertOk();

    Livewire::test(ListCooks::class)
        ->assertCanSeeTableRecords([$current, $forgotten, $old], inOrder: true)
        ->assertSee('Ended automatically')
        ->assertActionVisible(TestAction::make('endCook')->table($current))
        ->assertActionHidden(TestAction::make('endCook')->table($old));
});

it('starts a cook on the CyberQ with the pit and first food probe ticked', function () {
    $guru = Guru::factory()->withProbes()->create();

    Livewire::test(CreateCook::class)
        ->assertSchemaStateSet(['guru_id' => $guru->id])
        ->fillForm(['name' => 'Ribs', 'started_at' => now()])
        ->call('create')
        ->assertHasNoFormErrors();

    $cook = Cook::current();

    expect($cook)->name->toBe('Ribs')->guru_id->toBe($guru->id)
        ->and($cook->probesInUse()->pluck('identifier')->all())->toBe([Probe::PIT, Probe::FOOD1]);
});

it('starts a cook with whichever probes are in use', function () {
    $guru = Guru::factory()->withProbes()->create();
    $chosen = $guru->probes->whereIn('identifier', [Probe::FOOD2, Probe::FOOD3])->pluck('id')->map(fn ($id) => (string) $id)->values()->all();

    Livewire::test(CreateCook::class)
        ->fillForm(['name' => 'Two briskets', 'started_at' => now(), 'probes' => $chosen])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Cook::current()->probesInUse()->pluck('identifier')->all())->toBe([Probe::FOOD2, Probe::FOOD3]);
});

it('needs at least one probe', function () {
    Guru::factory()->withProbes()->create();

    Livewire::test(CreateCook::class)
        ->fillForm(['name' => 'Nothing', 'started_at' => now(), 'probes' => []])
        ->call('create')
        ->assertHasFormErrors(['probes' => 'required']);
});

it('shows every probe ticked for cooks from before probes could be chosen', function () {
    $cook = Cook::factory()->ended()->create();

    Livewire::test(EditCook::class, ['record' => $cook->getRouteKey()])
        ->assertSet('data.probes', fn (array $probes) => count($probes) === 4);
});

it('needs a name to start a cook', function () {
    Guru::factory()->withProbes()->create();

    Livewire::test(CreateCook::class)
        ->fillForm(['name' => ''])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required']);
});

it('edits a cook, including when it ended', function () {
    $cook = Cook::factory()->ended()->create();

    Livewire::test(EditCook::class, ['record' => $cook->getRouteKey()])
        ->fillForm(['name' => 'Renamed', 'ended_at' => $cook->started_at->copy()->addHours(10)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cook->refresh())->name->toBe('Renamed')
        ->and($cook->ended_at->equalTo($cook->started_at->copy()->addHours(10)))->toBeTrue();
});

it('does not let a cook end before it started', function () {
    $cook = Cook::factory()->ended()->create();

    Livewire::test(EditCook::class, ['record' => $cook->getRouteKey()])
        ->fillForm(['ended_at' => $cook->started_at->copy()->subHour()])
        ->call('save')
        ->assertHasFormErrors(['ended_at' => 'after']);
});

it('ends a cook from the list', function () {
    $cook = Cook::factory()->create();

    Livewire::test(ListCooks::class)->callTableAction('endCook', $cook);

    expect($cook->refresh()->ended_at->equalTo(now()))->toBeTrue();
});

it('deletes cooks along with their readings', function () {
    $cook = Cook::factory()->ended()->create();
    recordReadings($cook, Probe::PIT, [0 => [2000, 2250]]);

    Livewire::test(ListCooks::class)->callTableBulkAction(DeleteBulkAction::class, [$cook]);

    expect(Cook::count())->toBe(0)
        ->and(Reading::count())->toBe(0);
});

it('shows a previous cook with its summary and graphs', function () {
    $cook = Cook::factory()->ended()->create(['name' => 'Birthday brisket']);
    recordReadings($cook, Probe::PIT, [0 => [2200, 2250], 60 => [2300, 2250]]);
    recordReadings($cook, Probe::FOOD1, [0 => [500, 2030], 60 => [2040, 2030]]);

    $this->get("/cooks/{$cook->id}")
        ->assertOk()
        ->assertSee('Birthday brisket')
        ->assertSee('Average pit')
        ->assertSee('225')
        ->assertSee('Hit its target 1 hour in');

    $options = Livewire::test(CookTemperatureChart::class, ['record' => $cook])->get('options');

    expect(array_column($options['series'], 'name'))->toContain('Pit', 'Food 1');
});

it('does not keep refreshing the graphs of a finished cook', function () {
    $cook = Cook::factory()->ended()->create();

    Livewire::test(ViewCook::class, ['record' => $cook->getRouteKey()])
        ->assertActionHidden('endCook')
        ->assertActionVisible('compare');

    $chart = Livewire::test(CookTemperatureChart::class, ['record' => $cook])->instance();

    expect((fn () => $this->getPollingInterval())->call($chart))->toBeNull();
});

it('offers each probe as a checkbox when starting a cook', function () {
    Guru::factory()->withProbes()->create();

    $this->get('/cooks/create')
        ->assertOk()
        ->assertSeeInOrder(['Probes in use', 'Pit', 'Food 1', 'Food 2', 'Food 3', 'Only these are recorded']);
});

it('shows which probes a cook used', function () {
    $cook = Cook::factory()->ended()->create();
    $cook->useProbes([Probe::PIT, Probe::FOOD2]);

    $this->get("/cooks/{$cook->id}")
        ->assertOk()
        ->assertSeeInOrder(['Probes in use', 'Pit', 'Food 2']);
});

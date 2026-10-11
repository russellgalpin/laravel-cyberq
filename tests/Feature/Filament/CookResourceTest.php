<?php

use App\Enums\Meat;
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
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
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

it('starts a cook with a different meat on each food probe, and sends their targets', function () {
    Http::fake();
    Guru::factory()->withProbes()->create();

    Livewire::test(CreateCook::class)
        ->fillForm(['food1' => ['meat' => Meat::PulledPork->value]])
        ->assertSchemaStateSet(['food1.target' => 203, 'pit.target' => 250])
        ->fillForm(['food2' => ['in_use' => true]])
        ->fillForm(['food2' => ['meat' => Meat::Brisket->value, 'name' => 'Point end']])
        ->assertSchemaStateSet(['food2.target' => 203, 'pit.target' => 250])
        ->fillForm(['food2' => ['target' => 198], 'started_at' => now()])
        ->call('create')
        ->assertHasNoFormErrors();

    $cook = Cook::current();

    expect($cook->name)->toBe('Pulled pork and brisket')
        ->and($cook->probesInUse()->pluck('identifier')->all())->toBe([Probe::PIT, Probe::FOOD1, Probe::FOOD2])
        ->and($cook->probeMeat(Probe::FOOD1))->toBe(Meat::PulledPork)
        ->and($cook->probeName(Probe::FOOD1))->toBe('Pulled pork')
        ->and($cook->probeName(Probe::FOOD2))->toBe('Point end');

    Http::assertSent(fn (Request $request) => $request['COOK_SET'] == 250 && $request['FOOD1_SET'] == 203 && $request['FOOD2_SET'] == 198);
});

it('suggests a new meat\'s pit temperature only until one has been chosen', function () {
    Guru::factory()->withProbes()->create();

    Livewire::test(CreateCook::class)
        ->fillForm(['food1' => ['meat' => Meat::PulledPork->value]])
        ->fillForm(['food1' => ['meat' => Meat::WholeChicken->value]])
        ->assertSchemaStateSet(['pit.target' => 325, 'food1.target' => 165])
        ->fillForm(['pit' => ['target' => 300]])
        ->fillForm(['food1' => ['meat' => Meat::Turkey->value]])
        ->assertSchemaStateSet(['pit.target' => 300]);
});

it('keeps a typed name', function () {
    Http::fake();
    Guru::factory()->withProbes()->create();

    Livewire::test(CreateCook::class)
        ->fillForm(['name' => 'Smoky Joe\'s shoulder', 'food1' => ['meat' => Meat::LambShoulder->value], 'started_at' => now()])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Cook::current()->name)->toBe('Smoky Joe\'s shoulder');
});

it('warns when the cook starts but the CyberQ does not take the targets', function () {
    Http::fake(fn () => throw new ConnectionException('timed out'));
    Guru::factory()->withProbes()->create();

    Livewire::test(CreateCook::class)
        ->fillForm(['food1' => ['meat' => Meat::WholeChicken->value], 'started_at' => now()])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertNotified('The cook has started, but the CyberQ did not take the targets');

    expect(Cook::current()->name)->toBe('Whole chicken');
});

it('starts a cook with whichever probes are in use', function () {
    Guru::factory()->withProbes()->create();

    Livewire::test(CreateCook::class)
        ->fillForm(['name' => 'Two briskets', 'started_at' => now(), 'pit' => ['in_use' => false], 'food1' => ['in_use' => false], 'food2' => ['in_use' => true], 'food3' => ['in_use' => true]])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Cook::current()->probesInUse()->pluck('identifier')->all())->toBe([Probe::FOOD2, Probe::FOOD3]);
});

it('needs at least one probe', function () {
    Guru::factory()->withProbes()->create();

    Livewire::test(CreateCook::class)
        ->fillForm(['name' => 'Nothing', 'started_at' => now(), 'pit' => ['in_use' => false], 'food1' => ['in_use' => false]])
        ->call('create')
        ->assertHasFormErrors(['pit.in_use']);
});

it('shows every probe in use for cooks from before probes could be chosen', function () {
    $cook = Cook::factory()->ended()->create();

    Livewire::test(EditCook::class, ['record' => $cook->getRouteKey()])
        ->assertSchemaStateSet(['pit.in_use' => true, 'food1.in_use' => true, 'food2.in_use' => true, 'food3.in_use' => true]);
});

it('changes what a food probe is in after the cook has started', function () {
    $cook = Cook::factory()->create();
    $cook->useProbes([Probe::PIT, Probe::FOOD1], [Probe::FOOD1 => ['meat' => Meat::PulledPork]]);

    Livewire::test(EditCook::class, ['record' => $cook->getRouteKey()])
        ->assertSchemaStateSet(['food1.meat' => Meat::PulledPork, 'food2.in_use' => false])
        ->fillForm(['food1' => ['meat' => Meat::Brisket->value, 'name' => 'Flat']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($cook->fresh()->probeMeat(Probe::FOOD1))->toBe(Meat::Brisket)
        ->and($cook->fresh()->probeName(Probe::FOOD1))->toBe('Flat');
});

it('names a cook after the day when nothing says what it is', function () {
    Guru::factory()->withProbes()->create();

    Livewire::test(CreateCook::class)
        ->fillForm(['started_at' => now()])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Cook::current()->name)->toBe('Cook on '.now()->format('D j M'));
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

it('offers each probe, with what is cooking on it, when starting a cook', function () {
    Guru::factory()->withProbes()->create();

    $this->get('/cooks/create')
        ->assertOk()
        ->assertSeeInOrder(['Probes', 'Only probes in use are recorded', 'Pit', 'Food 1', 'Cooking', 'Pulled pork', 'Food 2', 'Food 3']);
});

it('shows which probes a cook used', function () {
    $cook = Cook::factory()->ended()->create();
    $cook->useProbes([Probe::PIT, Probe::FOOD2]);

    $this->get("/cooks/{$cook->id}")
        ->assertOk()
        ->assertSeeInOrder(['Probes in use', 'Pit', 'Food 2']);
});

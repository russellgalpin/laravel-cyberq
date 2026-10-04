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

it('starts a cook on the CyberQ', function () {
    $guru = Guru::factory()->create();

    Livewire::test(CreateCook::class)
        ->assertSchemaStateSet(['guru_id' => $guru->id])
        ->fillForm(['name' => 'Ribs', 'started_at' => now()])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Cook::current())->name->toBe('Ribs')->guru_id->toBe($guru->id);
});

it('needs a name to start a cook', function () {
    Guru::factory()->create();

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

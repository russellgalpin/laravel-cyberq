<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\ChartPalette;
use App\Filament\Widgets\CookComparisonChart;
use App\Models\Cook;
use App\Models\Probe;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class CompareCooks extends Dashboard
{
    use HasFiltersForm {
        mountHasFilters as mountFiltersFromUrlOrSession;
    }

    protected static string $routePath = 'compare';

    protected static ?string $title = 'Compare cooks';

    protected static ?string $navigationLabel = 'Compare cooks';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartLine;

    protected static ?int $navigationSort = 3;

    public function mountHasFilters(): void
    {
        $hasChosenFilters = filled($this->filters) || session()->has($this->getFiltersSessionKey());

        if (! $hasChosenFilters) {
            $this->filters = [
                'cooks' => Cook::query()->latest('started_at')->limit(3)->pluck('id')->all(),
                'probe' => Probe::FOOD1,
            ];
        }

        $this->mountFiltersFromUrlOrSession();
    }

    /** @param list<int> $cookIds */
    public static function urlFor(array $cookIds, string $probe = Probe::FOOD1): string
    {
        return static::getUrl(['filters' => ['cooks' => $cookIds, 'probe' => $probe]]);
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->columns(['md' => 3])
            ->components([
                Select::make('cooks')
                    ->label('Cooks')
                    ->multiple()
                    ->maxItems(CookComparisonChart::MAXIMUM_COOKS)
                    ->searchable()
                    ->options(fn () => Cook::query()
                        ->latest('started_at')
                        ->get()
                        ->mapWithKeys(fn (Cook $cook) => [$cook->id => "{$cook->name} ({$cook->started_at?->format('j M Y')})"]))
                    ->columnSpan(['md' => 2]),
                Select::make('probe')
                    ->label('Probe')
                    ->options(ChartPalette::PROBE_LABELS)
                    ->selectablePlaceholder(false),
            ]);
    }

    public function getWidgets(): array
    {
        return [
            CookComparisonChart::class,
        ];
    }

    public function getColumns(): int|array
    {
        return 1;
    }
}

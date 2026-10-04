<?php

namespace App\Filament\Widgets;

use App\Models\Cook;
use App\Models\Probe;
use App\Support\CookSnapshot;
use App\Support\ProbeSnapshot;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CurrentCookStats extends StatsOverviewWidget
{
    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $cook = Cook::current();

        if (! $cook) {
            return [
                Stat::make('No cook in progress', '-')
                    ->description('Start a cook to begin recording the CyberQ.')
                    ->icon('heroicon-o-fire'),
            ];
        }

        $snapshot = new CookSnapshot($cook);

        return [
            $this->cookStat($snapshot),
            ...$snapshot->probes()->map(fn (ProbeSnapshot $probe) => $this->temperatureStat($probe))->all(),
        ];
    }

    private function cookStat(CookSnapshot $snapshot): Stat
    {
        $lastReadingAt = $snapshot->lastReadingAt();

        $stat = Stat::make($snapshot->cook->name, $snapshot->cook->durationForHumans() ?? '-')
            ->icon('heroicon-o-clock');

        if (! $lastReadingAt) {
            return $stat->description('Waiting for the first reading')->color('gray');
        }

        if ($snapshot->isStale()) {
            return $stat
                ->description("No readings since {$lastReadingAt->diffForHumans()} - is the CyberQ on?")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger');
        }

        return $stat->description("Last reading {$lastReadingAt->diffForHumans()}");
    }

    private function temperatureStat(ProbeSnapshot $probe): Stat
    {
        $stat = Stat::make($probe->label, number_format($probe->temperature, 1).'°F')
            ->chart($probe->recentTemperatures);

        if ($probe->target === null) {
            return $stat;
        }

        $description = 'Target '.round($probe->target).'°F';

        if ($probe->identifier === Probe::PIT) {
            return $stat->description($description)->color(match ($probe->pitHealth()) {
                'good' => 'success',
                'warning' => 'warning',
                default => 'danger',
            });
        }

        if ($probe->reachedTarget()) {
            return $stat->description("{$description} - done")->descriptionIcon('heroicon-m-check-circle')->color('success');
        }

        if ($probe->timeToTarget) {
            $description .= " - about {$probe->timeToTarget->forHumans(['short' => true, 'parts' => 2])} to go";
        }

        return $stat->description($description);
    }
}

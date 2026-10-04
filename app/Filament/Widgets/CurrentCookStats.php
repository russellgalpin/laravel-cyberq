<?php

namespace App\Filament\Widgets;

use App\Models\Cook;
use App\Models\Probe;
use App\Models\Reading;
use App\Support\TemperatureForecast;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Collection;

class CurrentCookStats extends StatsOverviewWidget
{
    public const int STALE_AFTER_MINUTES = 5;

    public const int FORECAST_WINDOW_MINUTES = 45;

    protected ?string $pollingInterval = '30s';

    protected int|string|array $columnSpan = 'full';

    /** @var array<string, Collection<int, Reading>> */
    private array $recentReadings = [];

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

        return array_filter([
            $this->cookStat($cook),
            $this->temperatureStat($cook, Probe::PIT),
            $this->temperatureStat($cook, Probe::FOOD1),
            $this->temperatureStat($cook, Probe::FOOD2),
            $this->temperatureStat($cook, Probe::FOOD3),
        ]);
    }

    private function cookStat(Cook $cook): Stat
    {
        $lastReadingAt = $cook->lastReadingAt();

        $stat = Stat::make($cook->name, $cook->durationForHumans() ?? '-')
            ->icon('heroicon-o-clock');

        if (! $lastReadingAt) {
            return $stat->description('Waiting for the first reading')->color('gray');
        }

        if ($lastReadingAt->lessThan(now()->subMinutes(self::STALE_AFTER_MINUTES))) {
            return $stat
                ->description("No readings since {$lastReadingAt->diffForHumans()} - is the CyberQ on?")
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger');
        }

        return $stat->description("Last reading {$lastReadingAt->diffForHumans()}");
    }

    private function temperatureStat(Cook $cook, string $identifier): ?Stat
    {
        $latest = $cook->latestReadingFor($identifier);

        if (! $latest) {
            return null;
        }

        $label = ChartPalette::PROBE_LABELS[$identifier];
        $stat = Stat::make($label, number_format($latest->temperature_in_fahrenheit, 1).'°F')
            ->chart($this->sparkline($cook, $identifier));

        $target = $latest->set_point_in_fahrenheit;

        if ($target === null) {
            return $stat;
        }

        $description = "Target {$this->degrees($target)}";

        if ($identifier === Probe::PIT) {
            return $stat->description($description)->color($this->pitColour($latest->temperature_in_fahrenheit, $target));
        }

        if ($latest->temperature_in_fahrenheit >= $target) {
            return $stat->description("{$description} - done")->descriptionIcon('heroicon-m-check-circle')->color('success');
        }

        $eta = $this->forecast($cook, $identifier)->timeToReach($target);

        if ($eta) {
            $description .= " - about {$eta->forHumans(['short' => true, 'parts' => 2])} to go";
        }

        return $stat->description($description);
    }

    private function forecast(Cook $cook, string $identifier): TemperatureForecast
    {
        return new TemperatureForecast(
            $this->recentReadings($cook, $identifier)->map(fn (Reading $reading) => [
                'at' => $reading->created_at,
                'fahrenheit' => $reading->temperature_in_fahrenheit,
            ])
        );
    }

    /** @return list<float> */
    private function sparkline(Cook $cook, string $identifier): array
    {
        return $this->recentReadings($cook, $identifier)
            ->pluck('temperature_in_fahrenheit')
            ->all();
    }

    /** @return Collection<int, Reading> */
    private function recentReadings(Cook $cook, string $identifier): Collection
    {
        return $this->recentReadings[$identifier] ??= $cook->readings()
            ->whereRelation('probe', 'identifier', $identifier)
            ->where('created_at', '>=', now()->subMinutes(self::FORECAST_WINDOW_MINUTES))
            ->orderBy('created_at')
            ->get();
    }

    private function pitColour(float $temperature, float $target): string
    {
        $difference = abs($temperature - $target);

        if ($difference <= 15) {
            return 'success';
        }

        if ($difference <= 30) {
            return 'warning';
        }

        return 'danger';
    }

    private function degrees(float $fahrenheit): string
    {
        return round($fahrenheit).'°F';
    }
}

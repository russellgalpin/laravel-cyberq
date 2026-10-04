<?php

namespace App\Support\LiveActivities;

use App\Models\Cook;
use App\Models\Probe;
use App\Support\CookSnapshot;
use App\Support\ProbeSnapshot;
use Carbon\CarbonInterface;

/**
 * The Live Activity's data, shaped to decode straight into the app's
 * CookActivityAttributes. ActivityKit decodes dates as seconds since
 * 1 January 2001, not since 1970.
 */
class LiveActivityContent
{
    private const int APPLE_REFERENCE_DATE = 978_307_200;

    /** @return array<string, mixed> */
    public static function attributes(Cook $cook): array
    {
        return [
            'cookId' => $cook->id,
            'cookName' => $cook->name,
            'startedAt' => self::appleDate($cook->started_at),
        ];
    }

    /** @return array<string, mixed> */
    public static function state(CookSnapshot $snapshot): array
    {
        $probes = $snapshot->probes();
        $pit = $probes->firstWhere('identifier', Probe::PIT);
        $foods = $probes->reject(fn (ProbeSnapshot $probe) => $probe->identifier === Probe::PIT)->values();

        $longestToGo = $foods
            ->reject(fn (ProbeSnapshot $probe) => $probe->reachedTarget())
            ->map(fn (ProbeSnapshot $probe) => $probe->timeToTarget)
            ->filter()
            ->max(fn ($interval) => $interval->totalSeconds);

        return [
            'pit' => $pit?->temperature,
            'pitTarget' => $pit?->target,
            'foods' => $foods->map(fn (ProbeSnapshot $probe) => [
                'label' => $probe->label,
                'temperature' => $probe->temperature,
                'target' => $probe->target,
                'done' => $probe->reachedTarget(),
            ])->all(),
            'estimatedDoneAt' => $longestToGo ? self::appleDate(now()->addSeconds((int) $longestToGo)) : null,
            'lastReadingAt' => self::appleDate($snapshot->lastReadingAt()),
            'stale' => $snapshot->isStale(),
            'ended' => ! $snapshot->cook->in_progress,
        ];
    }

    private static function appleDate(?CarbonInterface $date): ?float
    {
        return $date ? (float) ($date->getTimestamp() - self::APPLE_REFERENCE_DATE) : null;
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Cook;
use App\Models\Probe;
use App\Support\CookSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cook */
class CookResource extends JsonResource
{
    private bool $withSummary = false;

    public function withSummary(): static
    {
        $this->withSummary = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'guru_id' => $this->guru_id,
            'started_at' => $this->started_at?->toIso8601String(),
            'ended_at' => $this->ended_at?->toIso8601String(),
            'ended_automatically' => $this->ended_automatically,
            'in_progress' => $this->in_progress,
            'duration_minutes' => $this->duration() ? (int) $this->duration()->totalMinutes : null,
            'summary' => $this->when($this->withSummary, fn () => $this->summary()),
        ];
    }

    /** @return array<string, mixed> */
    private function summary(): array
    {
        $summary = CookSummary::for($this->resource);

        return [
            'average_pit' => $summary->averagePit(),
            'minimum_pit' => $summary->minimumPit(),
            'maximum_pit' => $summary->maximumPit(),
            'pit_on_target_percent' => $summary->pitStability(),
            'pit_tolerance_fahrenheit' => CookSummary::PIT_TOLERANCE_FAHRENHEIT,
            'average_fan_output' => $summary->averageFanOutput(),
            'food_probes' => collect([Probe::FOOD1, Probe::FOOD2, Probe::FOOD3])
                ->filter(fn (string $identifier) => $summary->peakTemperature($identifier) !== null)
                ->map(fn (string $identifier) => [
                    'identifier' => $identifier,
                    'label' => Probe::LABELS[$identifier],
                    'peak' => $summary->peakTemperature($identifier),
                    'reached_target_at' => $summary->reachedTargetAt($identifier)?->toIso8601String(),
                ])
                ->values(),
        ];
    }
}

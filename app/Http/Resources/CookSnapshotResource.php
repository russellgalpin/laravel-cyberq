<?php

namespace App\Http\Resources;

use App\Models\Probe;
use App\Support\CookSnapshot;
use App\Support\ProbeSnapshot;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property CookSnapshot $resource */
class CookSnapshotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $snapshot = $this->resource;

        return [
            'cook' => new CookResource($snapshot->cook),
            'last_reading_at' => $snapshot->lastReadingAt()?->toIso8601String(),
            'stale' => $snapshot->isStale(),
            'fan_output' => $snapshot->fanOutput(),
            'probes' => $snapshot->probes()->map(fn (ProbeSnapshot $probe) => [
                'identifier' => $probe->identifier,
                'label' => $probe->label,
                'temperature' => $probe->temperature,
                'target' => $probe->target,
                'reached_target' => $probe->reachedTarget(),
                'minutes_to_target' => $probe->timeToTarget ? (int) $probe->timeToTarget->totalMinutes : null,
                'pit_health' => $probe->identifier === Probe::PIT ? $probe->pitHealth() : null,
                'recent' => $probe->recentTemperatures,
            ])->all(),
        ];
    }
}

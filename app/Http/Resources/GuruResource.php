<?php

namespace App\Http\Resources;

use App\Models\Guru;
use App\Models\Probe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Guru */
class GuruResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'probes' => $this->probes
                ->filter(fn (Probe $probe) => $probe->isTemperature())
                ->sortBy(fn (Probe $probe) => array_search($probe->identifier, Probe::TEMPERATURES, true))
                ->map(fn (Probe $probe) => [
                    'identifier' => $probe->identifier,
                    'label' => $probe->label(),
                    'in_use_by_default' => in_array($probe->identifier, Probe::DEFAULT_FOR_NEW_COOKS, true),
                ])
                ->values(),
        ];
    }
}

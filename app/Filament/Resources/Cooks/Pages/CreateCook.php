<?php

namespace App\Filament\Resources\Cooks\Pages;

use App\Filament\Resources\Cooks\CookResource;
use App\Filament\Resources\Cooks\Schemas\CookForm;
use App\Models\Cook;
use App\Services\Guru\CyberQUnreachable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateCook extends CreateRecord
{
    protected static string $resource = CookResource::class;

    /** @var array<string, mixed> */
    private array $probeState = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->probeState = $data;

        return [
            ...CookForm::withoutProbeFields($data),
            'name' => filled($data['name'] ?? null) ? $data['name'] : Cook::nameFor(CookForm::meatsFromState($data)),
        ];
    }

    protected function afterCreate(): void
    {
        /** @var Cook $cook */
        $cook = $this->record;
        $probes = CookForm::probesFromState($this->probeState);

        $cook->useProbes($probes['identifiers'], $probes['foods']);

        try {
            $cook->sendTargets($probes['pitTarget'], $probes['foodTargets']);
        } catch (CyberQUnreachable $exception) {
            Notification::make()
                ->title('The cook has started, but the CyberQ did not take the targets')
                ->body($exception->getMessage())
                ->warning()
                ->persistent()
                ->send();
        }
    }
}

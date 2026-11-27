<?php

namespace App\Filament\Resources\Cooks\Pages;

use App\Filament\Resources\Cooks\CookResource;
use App\Filament\Resources\Cooks\Schemas\CookForm;
use App\Models\Cook;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCook extends EditRecord
{
    protected static string $resource = CookResource::class;

    /** @var array<string, mixed> */
    private array $probeState = [];

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Cook $cook */
        $cook = $this->record;

        return [...$data, ...CookForm::stateFromCook($cook)];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->probeState = $data;

        return CookForm::withoutProbeFields($data);
    }

    protected function afterSave(): void
    {
        /** @var Cook $cook */
        $cook = $this->record;
        $probes = CookForm::probesFromState($this->probeState);

        $cook->useProbes($probes['identifiers'], $probes['foods']);

        foreach ($probes['wrappedAt'] as $identifier => $wrappedAt) {
            // The form only shows minutes, so a wrap logged from the app keeps its seconds unless it was changed.
            if ($cook->probeWrappedAt($identifier)?->format('Y-m-d H:i') === $wrappedAt?->format('Y-m-d H:i')) {
                continue;
            }

            $cook->wrap($identifier, $wrappedAt);
        }

        foreach ($probes['wrapNotes'] as $identifier => $note) {
            if ($cook->probeWrappedAt($identifier) === null) {
                continue;
            }

            $cook->noteWrap($identifier, $note);
        }
    }
}

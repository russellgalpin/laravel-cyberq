<?php

namespace App\Filament\Resources\Cooks\Pages;

use App\Filament\Resources\Cooks\CookResource;
use App\Models\Cook;
use App\Services\Guru\CyberQUnreachable;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;

class CreateCook extends CreateRecord
{
    protected static string $resource = CookResource::class;

    /** @var array{pit_target?: float|int|string|null, food_target?: float|int|string|null} */
    private array $targets = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->targets = Arr::only($data, ['pit_target', 'food_target']);

        return Arr::except($data, ['pit_target', 'food_target']);
    }

    protected function afterCreate(): void
    {
        /** @var Cook $cook */
        $cook = $this->record;

        try {
            $cook->sendTargets(self::fahrenheit($this->targets['pit_target'] ?? null), self::fahrenheit($this->targets['food_target'] ?? null));
        } catch (CyberQUnreachable $exception) {
            Notification::make()
                ->title('The cook has started, but the CyberQ did not take the targets')
                ->body($exception->getMessage())
                ->warning()
                ->persistent()
                ->send();
        }
    }

    private static function fahrenheit(float|int|string|null $value): ?float
    {
        return blank($value) ? null : (float) $value;
    }
}

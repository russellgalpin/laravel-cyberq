<?php

namespace App\Filament\Actions;

use App\Models\Cook;
use App\Models\Guru;
use App\Services\Guru\CyberQUnreachable;
use App\Support\TargetTemperatures;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;

class SetTargetsAction
{
    public const float MINIMUM_FAHRENHEIT = 32;

    public const float MAXIMUM_FAHRENHEIT = 475;

    public static function make(): Action
    {
        return Action::make('setTargets')
            ->label('Set targets')
            ->icon('heroicon-o-adjustments-horizontal')
            ->modalDescription('Changes are sent straight to the CyberQ.')
            ->visible(fn () => self::guru() !== null)
            ->fillForm(function (Action $action) {
                try {
                    return TargetTemperatures::fromDevice(self::guru()->cyberQ()->status());
                } catch (CyberQUnreachable $exception) {
                    Notification::make()->title('The CyberQ is not responding')->body($exception->getMessage())->danger()->send();

                    $action->cancel();
                }
            })
            ->schema([
                Grid::make(2)->schema([
                    self::temperatureInput('COOK_SET', 'Pit'),
                    self::temperatureInput('FOOD1_SET', 'Food 1'),
                    self::temperatureInput('FOOD2_SET', 'Food 2'),
                    self::temperatureInput('FOOD3_SET', 'Food 3'),
                ]),
            ])
            ->action(function (array $data, Action $action) {
                try {
                    self::guru()->cyberQ()->update(array_filter($data, fn ($value) => $value !== null));
                } catch (CyberQUnreachable $exception) {
                    Notification::make()->title('The targets were not changed')->body($exception->getMessage())->danger()->send();

                    $action->halt();
                }

                Notification::make()->title('Targets sent to the CyberQ')->success()->send();
            });
    }

    public static function temperatureInput(string $field, string $label): TextInput
    {
        return TextInput::make($field)
            ->label($label)
            ->numeric()
            ->step(1)
            ->minValue(self::MINIMUM_FAHRENHEIT)
            ->maxValue(self::MAXIMUM_FAHRENHEIT)
            ->suffix('°F');
    }

    public static function guru(): ?Guru
    {
        return Cook::current()?->guru ?? Guru::query()->first();
    }
}

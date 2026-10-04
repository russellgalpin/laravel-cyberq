<?php

namespace App\Filament\Pages;

use App\Enums\RampProbe;
use App\Enums\TimeoutAction;
use App\Filament\Actions\SetTargetsAction;
use App\Services\Guru\CyberQUnreachable;
use App\Support\ControllerSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * @property-read Schema $form
 */
class Controller extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;

    protected static ?string $navigationLabel = 'CyberQ controller';

    protected static ?string $title = 'CyberQ controller';

    protected static ?string $slug = 'controller';

    protected static ?int $navigationSort = 2;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<string, mixed> */
    public array $deviceValues = [];

    public ?string $unreachableReason = null;

    public function mount(): void
    {
        $this->loadFromDevice();
    }

    public function loadFromDevice(): void
    {
        $guru = SetTargetsAction::guru();

        if (! $guru) {
            $this->unreachableReason = 'No CyberQ has been set up yet.';

            return;
        }

        try {
            $this->deviceValues = ControllerSettings::fromDevice($guru->cyberQ()->settings());
        } catch (CyberQUnreachable $exception) {
            $this->unreachableReason = $exception->getMessage();
            $this->form->fill();

            return;
        }

        $this->unreachableReason = null;
        $this->form->fill($this->deviceValues);
    }

    public function save(): void
    {
        $changes = ControllerSettings::changesForDevice($this->deviceValues, $this->form->getState());

        if ($changes === []) {
            Notification::make()->title('Nothing has changed')->info()->send();

            return;
        }

        try {
            SetTargetsAction::guru()->cyberQ()->update($changes);
        } catch (CyberQUnreachable $exception) {
            Notification::make()->title('The CyberQ was not updated')->body($exception->getMessage())->danger()->send();

            return;
        }

        $count = count($changes);

        Notification::make()
            ->title("Sent {$count} ".str('change')->plural($count).' to the CyberQ')
            ->success()
            ->send();

        $this->loadFromDevice();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->disabled(fn () => $this->unreachableReason !== null)
            ->components([
                Section::make('Targets')
                    ->columns(['sm' => 2, 'lg' => 4])
                    ->schema([
                        SetTargetsAction::temperatureInput('COOK_SET', 'Pit'),
                        SetTargetsAction::temperatureInput('FOOD1_SET', 'Food 1'),
                        SetTargetsAction::temperatureInput('FOOD2_SET', 'Food 2'),
                        SetTargetsAction::temperatureInput('FOOD3_SET', 'Food 3'),
                    ]),
                Section::make('Probe names')
                    ->description('Shown on the CyberQ display.')
                    ->columns(['sm' => 2, 'lg' => 4])
                    ->collapsible()
                    ->schema([
                        TextInput::make('COOK_NAME')->label('Pit')->maxLength(31),
                        TextInput::make('FOOD1_NAME')->label('Food 1')->maxLength(31),
                        TextInput::make('FOOD2_NAME')->label('Food 2')->maxLength(31),
                        TextInput::make('FOOD3_NAME')->label('Food 3')->maxLength(31),
                    ]),
                Section::make('Cook timer')
                    ->description('Counts down on the CyberQ. What happens when it reaches zero is set by "When the timer ends" below.')
                    ->collapsible()
                    ->schema([
                        TextInput::make(ControllerSettings::TIMER_FIELD)
                            ->label('Time remaining')
                            ->placeholder('HH:MM:SS')
                            ->regex('/^\d{1,2}:[0-5]\d:[0-5]\d$/')
                            ->validationMessages(['regex' => 'Use hours, minutes and seconds, like 04:30:00.']),
                    ]),
                Section::make('Control')
                    ->columns(['sm' => 2])
                    ->collapsible()
                    ->schema([
                        Select::make('TIMEOUT_ACTION')
                            ->label('When the timer ends')
                            ->options(TimeoutAction::class)
                            ->selectablePlaceholder(false),
                        SetTargetsAction::temperatureInput('COOKHOLD', 'Hold temperature')
                            ->helperText('The pit target used when the cook timer ends on "Hold", or a ramping food probe is done.'),
                        Select::make('COOK_RAMP')
                            ->label('Ramp')
                            ->options(RampProbe::class)
                            ->selectablePlaceholder(false)
                            ->helperText('Lowers the pit target as this food probe nears its target, so it coasts in without overshooting.'),
                        Toggle::make(ControllerSettings::OPEN_LID_FIELD)
                            ->label('Open lid detect')
                            ->helperText('Pauses the fan when a sudden pit temperature drop suggests the lid is open.')
                            ->inline(false),
                        TextInput::make('ALARMDEV')
                            ->label('Alarm deviation')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(100)
                            ->suffix('°F')
                            ->helperText('Alarm when the pit strays this far from its target.'),
                        TextInput::make('PROPBAND')
                            ->label('Proportional band')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(100)
                            ->suffix('°F')
                            ->helperText('How far below target the fan starts to slow down. Smaller is more aggressive.'),
                        TextInput::make('CYCTIME')
                            ->label('Cycle time')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(30)
                            ->suffix('seconds')
                            ->helperText('How often the fan output is recalculated.'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Callout::make('The CyberQ is not responding')
                ->description(fn () => $this->unreachableReason)
                ->danger()
                ->visible(fn () => $this->unreachableReason !== null),
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')
                            ->label('Send to CyberQ')
                            ->submit('save')
                            ->disabled(fn () => $this->unreachableReason !== null),
                    ]),
                ]),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reload')
                ->label('Reload from CyberQ')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->loadFromDevice()),
        ];
    }
}

<?php

namespace App\Filament\Resources\Cooks\Schemas;

use App\Enums\Meat;
use App\Filament\Actions\SetTargetsAction;
use App\Models\Cook;
use App\Models\Guru;
use App\Models\Probe;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class CookForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(['sm' => 1, 'xl' => 2])
                    ->columnSpanFull()
                    ->schema([
                        Select::make('meat')
                            ->options(Meat::class)
                            ->placeholder('Other')
                            ->helperText('Fills in the name and suggested temperatures, which can still be changed.')
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set, Meat|string|null $state, Meat|string|null $old) => self::useMeat($get, $set, $state, $old)),
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        Select::make('guru_id')
                            ->label('CyberQ')
                            ->relationship('guru', titleAttribute: 'name')
                            ->default(fn () => Guru::query()->value('id'))
                            ->live()
                            ->required(),
                        DateTimePicker::make('started_at')
                            ->default(now())
                            ->seconds(false)
                            ->required(),
                        DateTimePicker::make('ended_at')
                            ->seconds(false)
                            ->after('started_at')
                            ->visibleOn('edit'),
                        SetTargetsAction::temperatureInput('pit_target', 'Pit target')
                            ->helperText('Sent to the CyberQ when the cook starts.')
                            ->visibleOn('create'),
                        SetTargetsAction::temperatureInput('food_target', 'Food done at')
                            ->helperText('Set on every food probe in use.')
                            ->visibleOn('create'),
                        CheckboxList::make('probes')
                            ->label('Probes in use')
                            ->helperText('Only these are recorded. Fan output is always recorded.')
                            ->relationship(
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query, Get $get) => $query
                                    ->where('guru_id', $get('guru_id'))
                                    ->whereIn('identifier', Probe::TEMPERATURES)
                                    ->orderBy('identifier'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (Probe $probe) => $probe->label())
                            ->afterStateHydrated(function (CheckboxList $component, ?Cook $record, ?array $state) {
                                if ($record && blank($state)) {
                                    $component->state($record->probesInUse()->pluck('id')->map(fn (int $id) => (string) $id)->all());
                                }
                            })
                            ->default(fn (Get $get) => Probe::query()
                                ->where('guru_id', $get('guru_id') ?? Guru::query()->value('id'))
                                ->whereIn('identifier', Probe::DEFAULT_FOR_NEW_COOKS)
                                ->pluck('id')
                                ->all())
                            ->columns(['default' => 2, 'md' => 4])
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->rows(3)
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Fills in the meat's name, unless one has been typed, and its suggested targets. */
    private static function useMeat(Get $get, Set $set, Meat|string|null $state, Meat|string|null $old): void
    {
        $meat = self::meat($state);

        if (! $meat) {
            return;
        }

        $previousMeat = self::meat($old);

        if (blank($get('name')) || $get('name') === $previousMeat?->getLabel()) {
            $set('name', $meat->getLabel());
        }

        $set('pit_target', $meat->pitTarget());
        $set('food_target', $meat->foodTarget());
    }

    private static function meat(Meat|string|null $state): ?Meat
    {
        return $state instanceof Meat ?
            $state :
            Meat::tryFrom((string) $state);
    }
}

<?php

namespace App\Filament\Resources\Cooks\Schemas;

use App\Enums\Meat;
use App\Filament\Actions\SetTargetsAction;
use App\Models\Cook;
use App\Models\Guru;
use App\Models\Probe;
use Closure;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * The cook form. Each probe has its own fields under a key ("pit", "food1"
 * and so on) rather than being saved by Filament, because a food probe also
 * says what it is in; the create and edit pages save them with
 * probesFromState().
 */
class CookForm
{
    public const array PROBE_FIELDS = [
        'pit' => Probe::PIT,
        'food1' => Probe::FOOD1,
        'food2' => Probe::FOOD2,
        'food3' => Probe::FOOD3,
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(['sm' => 1, 'xl' => 2])
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->placeholder('Named after the food if left empty')
                            ->required(fn (string $operation) => $operation === 'edit')
                            ->maxLength(255),
                        Select::make('guru_id')
                            ->label('CyberQ')
                            ->relationship('guru', titleAttribute: 'name')
                            ->default(fn () => Guru::query()->value('id'))
                            ->required(),
                        DateTimePicker::make('started_at')
                            ->default(now())
                            ->seconds(false)
                            ->required(),
                        DateTimePicker::make('ended_at')
                            ->seconds(false)
                            ->after('started_at')
                            ->visibleOn('edit'),
                        Textarea::make('description')
                            ->rows(2)
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
                Section::make('Probes')
                    ->description('Only probes in use are recorded. Fan output is always recorded.')
                    ->columns(['default' => 1, 'lg' => 2])
                    ->columnSpanFull()
                    ->schema([
                        self::pitFields(),
                        self::foodFields('food1', 'Food 1', inUseByDefault: true),
                        self::foodFields('food2', 'Food 2'),
                        self::foodFields('food3', 'Food 3'),
                    ]),
            ]);
    }

    /**
     * The form's probe fields, filled from a cook. Cooks from before probes
     * could be chosen use every probe.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function stateFromCook(Cook $cook): array
    {
        $inUse = $cook->probesInUse()->keyBy('identifier');

        return collect(self::PROBE_FIELDS)
            ->map(fn (string $identifier) => [
                'in_use' => $inUse->has($identifier),
                'meat' => $inUse->get($identifier)?->pivot?->meat?->value,
                'name' => $inUse->get($identifier)?->pivot?->name,
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{
     *     identifiers: list<string>,
     *     foods: array<string, array{meat: ?string, name: ?string}>,
     *     pitTarget: ?float,
     *     foodTargets: array<string, ?float>
     * }
     */
    public static function probesFromState(array $data): array
    {
        $inUse = collect(self::PROBE_FIELDS)->filter(fn (string $identifier, string $field) => (bool) ($data[$field]['in_use'] ?? false));
        $foods = $inUse->except('pit');

        return [
            'identifiers' => $inUse->values()->all(),
            'foods' => $foods->mapWithKeys(fn (string $identifier, string $field) => [$identifier => [
                'meat' => $data[$field]['meat'] ?? null,
                'name' => $data[$field]['name'] ?? null,
            ]])->all(),
            'pitTarget' => self::fahrenheit($data['pit']['target'] ?? null),
            'foodTargets' => $foods->mapWithKeys(fn (string $identifier, string $field) => [$identifier => self::fahrenheit($data[$field]['target'] ?? null)])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<Meat>
     */
    public static function meatsFromState(array $data): array
    {
        return collect(self::PROBE_FIELDS)
            ->except('pit')
            ->keys()
            ->filter(fn (string $field) => (bool) ($data[$field]['in_use'] ?? false))
            ->map(fn (string $field) => self::meat($data[$field]['meat'] ?? null))
            ->filter()
            ->values()
            ->all();
    }

    /** @param array<string, mixed> $data */
    public static function withoutProbeFields(array $data): array
    {
        return collect($data)->except(array_keys(self::PROBE_FIELDS))->all();
    }

    private static function pitFields(): Fieldset
    {
        return Fieldset::make('Pit')
            ->columns(1)
            ->schema([
                Toggle::make('pit.in_use')
                    ->label('In use')
                    ->default(true)
                    ->live()
                    ->rules([fn (Get $get) => self::atLeastOneProbe($get)]),
                SetTargetsAction::temperatureInput('pit.target', 'Pit target')
                    ->helperText('Sent to the CyberQ when the cook starts. Picking a food suggests one.')
                    ->visible(fn (Get $get, string $operation) => $operation === 'create' && $get('pit.in_use')),
            ]);
    }

    private static function foodFields(string $field, string $label, bool $inUseByDefault = false): Fieldset
    {
        $inUse = fn (Get $get) => (bool) $get("{$field}.in_use");

        return Fieldset::make($label)
            ->columns(1)
            ->schema([
                Toggle::make("{$field}.in_use")
                    ->label('In use')
                    ->default($inUseByDefault)
                    ->live(),
                Grid::make(['default' => 1, 'sm' => 2])
                    ->visible($inUse)
                    ->schema([
                        Select::make("{$field}.meat")
                            ->label('Cooking')
                            ->options(Meat::class)
                            ->placeholder('Other')
                            ->live()
                            ->afterStateUpdated(fn (Get $get, Set $set, Meat|string|null $state, Meat|string|null $old) => self::useMeat($field, $get, $set, $state, $old)),
                        TextInput::make("{$field}.name")
                            ->label('Name')
                            ->placeholder(fn (Get $get) => self::meat($get("{$field}.meat"))?->getLabel() ?? 'e.g. Big shoulder')
                            ->maxLength(60),
                        SetTargetsAction::temperatureInput("{$field}.target", 'Done at')
                            ->visibleOn('create'),
                    ]),
            ]);
    }

    /** Suggests the meat's done temperature, and its pit temperature unless one has been chosen. */
    private static function useMeat(string $field, Get $get, Set $set, Meat|string|null $state, Meat|string|null $old): void
    {
        $meat = self::meat($state);

        if (! $meat) {
            return;
        }

        $set("{$field}.target", $meat->foodTarget());

        $pitTarget = $get('pit.target');

        if (blank($pitTarget) || (float) $pitTarget === (float) self::meat($old)?->pitTarget()) {
            $set('pit.target', $meat->pitTarget());
        }
    }

    private static function atLeastOneProbe(Get $get): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($get) {
            $anyInUse = collect(array_keys(self::PROBE_FIELDS))->contains(fn (string $field) => (bool) $get("{$field}.in_use"));

            if (! $anyInUse) {
                $fail('Choose at least one probe.');
            }
        };
    }

    private static function meat(Meat|string|null $state): ?Meat
    {
        return $state instanceof Meat ?
            $state :
            Meat::tryFrom((string) $state);
    }

    private static function fahrenheit(mixed $value): ?float
    {
        return blank($value) ? null : (float) $value;
    }
}

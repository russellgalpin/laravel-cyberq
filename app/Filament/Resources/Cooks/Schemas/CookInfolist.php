<?php

namespace App\Filament\Resources\Cooks\Schemas;

use App\Filament\Widgets\ChartPalette;
use App\Models\Cook;
use App\Models\Probe;
use App\Support\CookSummary;
use Carbon\CarbonInterface;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CookInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(['lg' => 2])
            ->components([
                Section::make('Cook')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('guru.name')->label('CyberQ'),
                        IconEntry::make('in_progress')->label('In progress')->boolean(),
                        TextEntry::make('started_at')->dateTime('D j M Y, H:i'),
                        TextEntry::make('ended_at')
                            ->dateTime('D j M Y, H:i')
                            ->placeholder('In progress')
                            ->helperText(fn (Cook $record) => $record->ended_automatically ? 'Ended automatically after the CyberQ stopped reporting.' : null),
                        TextEntry::make('duration')
                            ->state(fn (Cook $record) => $record->durationForHumans(short: false)),
                        TextEntry::make('description')->placeholder('-')->columnSpanFull(),
                    ]),
                Section::make('Summary')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('average_pit')
                            ->label('Average pit')
                            ->state(fn (Cook $record) => self::summary($record)->averagePit())
                            ->suffix('°F')
                            ->placeholder('-'),
                        TextEntry::make('pit_range')
                            ->label('Pit range')
                            ->state(fn (Cook $record) => self::pitRange($record))
                            ->placeholder('-'),
                        TextEntry::make('pit_stability')
                            ->label('Pit on target')
                            ->state(fn (Cook $record) => self::summary($record)->pitStability())
                            ->suffix('%')
                            ->helperText('Readings within '.CookSummary::PIT_TOLERANCE_FAHRENHEIT.'°F of the target')
                            ->placeholder('-'),
                        TextEntry::make('average_fan')
                            ->label('Average fan output')
                            ->state(fn (Cook $record) => self::summary($record)->averageFanOutput())
                            ->suffix('%')
                            ->placeholder('-'),
                        ...collect([Probe::FOOD1, Probe::FOOD2, Probe::FOOD3])
                            ->map(fn (string $identifier) => self::foodEntry($identifier))
                            ->all(),
                    ]),
            ]);
    }

    private static function foodEntry(string $identifier): TextEntry
    {
        return TextEntry::make(strtolower($identifier))
            ->label(ChartPalette::PROBE_LABELS[$identifier].' peak')
            ->state(fn (Cook $record) => self::summary($record)->peakTemperature($identifier))
            ->suffix('°F')
            ->helperText(function (Cook $record) use ($identifier) {
                $reachedAt = self::summary($record)->reachedTargetAt($identifier);

                if (! $reachedAt || ! $record->started_at) {
                    return null;
                }

                return 'Hit its target '.$record->started_at->diffForHumans($reachedAt, ['syntax' => CarbonInterface::DIFF_ABSOLUTE, 'parts' => 2]).' in';
            })
            ->visible(fn (Cook $record) => self::summary($record)->peakTemperature($identifier) !== null);
    }

    private static function pitRange(Cook $cook): ?string
    {
        $minimum = self::summary($cook)->minimumPit();

        if ($minimum === null) {
            return null;
        }

        return "{$minimum}°F to ".self::summary($cook)->maximumPit().'°F';
    }

    private static function summary(Cook $cook): CookSummary
    {
        return once(fn () => CookSummary::for($cook));
    }
}

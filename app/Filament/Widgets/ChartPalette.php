<?php

namespace App\Filament\Widgets;

use App\Models\Probe;

/**
 * Categorical series colours, always assigned in this order so a probe keeps
 * its colour on every chart. Validated for colour-vision deficiency.
 */
class ChartPalette
{
    public const array PROBES = [
        Probe::PIT => '#2a78d6',
        Probe::FOOD1 => '#eb6834',
        Probe::FOOD2 => '#1baf7a',
        Probe::FOOD3 => '#eda100',
    ];

    public const array SERIES = ['#2a78d6', '#eb6834', '#1baf7a', '#eda100', '#e87ba4', '#008300', '#4a3aa7', '#e34948'];

    public const array PROBE_LABELS = [
        Probe::PIT => 'Pit',
        Probe::FOOD1 => 'Food 1',
        Probe::FOOD2 => 'Food 2',
        Probe::FOOD3 => 'Food 3',
    ];
}

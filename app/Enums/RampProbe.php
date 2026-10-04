<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum RampProbe: int implements HasLabel
{
    case Off = 0;
    case Food1 = 1;
    case Food2 = 2;
    case Food3 = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::Off => 'Off',
            self::Food1 => 'Food 1',
            self::Food2 => 'Food 2',
            self::Food3 => 'Food 3',
        };
    }
}

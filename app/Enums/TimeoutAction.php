<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TimeoutAction: int implements HasLabel
{
    case NoAction = 0;
    case Hold = 1;
    case Alarm = 2;
    case Shutdown = 3;

    public function getLabel(): string
    {
        return match ($this) {
            self::NoAction => 'No action',
            default => $this->name,
        };
    }
}

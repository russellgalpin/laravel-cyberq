<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProbeStatus: int implements HasColor, HasLabel
{
    case Ok = 0;
    case High = 1;
    case Low = 2;
    case Done = 3;
    case Error = 4;
    case Hold = 5;
    case Alarm = 6;
    case Shutdown = 7;

    public function getLabel(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Error => 'Not connected',
            default => $this->name,
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Ok, self::Done => 'success',
            self::Hold => 'info',
            self::High, self::Low => 'warning',
            self::Error => 'gray',
            self::Alarm, self::Shutdown => 'danger',
        };
    }
}

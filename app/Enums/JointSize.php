<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/** How big a joint is, which changes how long it takes far more than its meat does. */
enum JointSize: string implements HasLabel
{
    case Small = 'small';
    case Medium = 'medium';
    case Large = 'large';

    public function getLabel(): string
    {
        return match ($this) {
            self::Small => 'Small',
            self::Medium => 'Medium',
            self::Large => 'Large',
        };
    }
}

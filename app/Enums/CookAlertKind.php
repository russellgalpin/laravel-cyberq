<?php

namespace App\Enums;

enum CookAlertKind: string
{
    case PitHigh = 'pit_high';
    case PitLow = 'pit_low';
    case FoodNearlyReady = 'food_nearly_ready';
    case FoodReady = 'food_ready';
    case CyberQOffline = 'cyberq_offline';

    /** The push_devices switch that decides whether a phone gets this alert. */
    public function preference(): string
    {
        return match ($this) {
            self::PitHigh, self::PitLow => 'pit_alerts',
            self::FoodNearlyReady, self::FoodReady => 'food_alerts',
            self::CyberQOffline => 'offline_alerts',
        };
    }
}

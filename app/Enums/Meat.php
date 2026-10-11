<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Common low-and-slow cooks, with the pit and done temperatures (degrees F)
 * a new cook starts from. Both can be changed before the cook starts.
 */
enum Meat: string implements HasLabel
{
    case PulledPork = 'pulled_pork';
    case Brisket = 'brisket';
    case LambShoulder = 'lamb_shoulder';
    case PorkRibs = 'pork_ribs';
    case BeefShortRibs = 'beef_short_ribs';
    case PorkBelly = 'pork_belly';
    case WholeChicken = 'whole_chicken';
    case ChickenThighs = 'chicken_thighs';
    case Turkey = 'turkey';
    case Gammon = 'gammon';
    case BeefJoint = 'beef_joint';
    case Salmon = 'salmon';

    public function getLabel(): string
    {
        return match ($this) {
            self::PulledPork => 'Pulled pork',
            self::Brisket => 'Brisket',
            self::LambShoulder => 'Lamb shoulder',
            self::PorkRibs => 'Pork ribs',
            self::BeefShortRibs => 'Beef short ribs',
            self::PorkBelly => 'Pork belly',
            self::WholeChicken => 'Whole chicken',
            self::ChickenThighs => 'Chicken thighs',
            self::Turkey => 'Turkey',
            self::Gammon => 'Gammon',
            self::BeefJoint => 'Beef joint (medium rare)',
            self::Salmon => 'Hot-smoked salmon',
        };
    }

    public function pitTarget(): int
    {
        return match ($this) {
            self::WholeChicken => 325,
            self::ChickenThighs, self::Turkey => 300,
            self::BeefShortRibs => 275,
            self::BeefJoint, self::Salmon => 225,
            default => 250,
        };
    }

    public function foodTarget(): int
    {
        return match ($this) {
            self::PulledPork, self::Brisket, self::BeefShortRibs => 203,
            self::LambShoulder => 200,
            self::PorkRibs => 198,
            self::PorkBelly => 195,
            self::ChickenThighs => 180,
            self::WholeChicken, self::Turkey => 165,
            self::Gammon => 160,
            self::Salmon => 145,
            self::BeefJoint => 130,
        };
    }

    /** @return array{
     *     value: string,
     *     label: string,
     *     pit_target: int,
     *     food_target: int
     * } */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->getLabel(),
            'pit_target' => $this->pitTarget(),
            'food_target' => $this->foodTarget(),
        ];
    }
}

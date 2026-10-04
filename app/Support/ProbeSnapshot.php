<?php

namespace App\Support;

use Carbon\CarbonInterval;

readonly class ProbeSnapshot
{
    /** @param list<float> $recentTemperatures */
    public function __construct(
        public string $identifier,
        public string $label,
        public float $temperature,
        public ?float $target,
        public ?CarbonInterval $timeToTarget,
        public array $recentTemperatures,
    ) {}

    public function reachedTarget(): bool
    {
        return $this->target !== null && $this->temperature >= $this->target;
    }

    /**
     * How far the pit is from its target: within 15°F is fine, within 30°F needs watching.
     */
    public function pitHealth(): ?string
    {
        if ($this->target === null) {
            return null;
        }

        $difference = abs($this->temperature - $this->target);

        if ($difference <= 15) {
            return 'good';
        }

        if ($difference <= 30) {
            return 'warning';
        }

        return 'critical';
    }
}

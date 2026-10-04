<?php

namespace App\Services\Guru;

use App\Enums\ProbeStatus;
use App\Models\Probe;

readonly class DeviceStatus
{
    /** @param array<string, string> $values */
    public function __construct(public array $values) {}

    public function raw(?string $key): ?string
    {
        return $this->values[$key] ?? null;
    }

    public function integer(?string $key): ?int
    {
        $value = $this->raw($key);

        return is_numeric($value) ? (int) $value : null;
    }

    public function fahrenheit(?string $key): ?float
    {
        $tenths = $this->integer($key);

        return $tenths === null ? null : $tenths / 10;
    }

    public function temperature(string $probeIdentifier): ?float
    {
        return $this->fahrenheit($probeIdentifier);
    }

    public function setPoint(string $probeIdentifier): ?float
    {
        return $this->fahrenheit(Probe::setPointKeyFor($probeIdentifier));
    }

    public function probeStatus(string $probeIdentifier): ?ProbeStatus
    {
        $status = $this->integer(Probe::statusKeyFor($probeIdentifier));

        return $status === null ? null : ProbeStatus::tryFrom($status);
    }

    public function fanOutput(): ?int
    {
        return $this->integer(Probe::FAN_OUTPUT);
    }
}

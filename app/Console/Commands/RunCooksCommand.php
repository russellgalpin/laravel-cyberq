<?php

namespace App\Console\Commands;

use App\Models\Cook;
use App\Models\Probe;
use App\Models\Reading;
use App\Services\Guru\CyberQUnreachable;
use App\Services\Guru\DeviceStatus;
use App\Support\Alerts\CookAlertMonitor;
use App\Support\LiveActivities\LiveActivityBroadcaster;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

#[Signature('cooks:run')]
#[Description('Record a reading from the CyberQ for every cook in progress')]
class RunCooksCommand extends Command
{
    public function __construct(
        private readonly LiveActivityBroadcaster $liveActivities,
        private readonly CookAlertMonitor $alerts,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $activeCooks = Cook::query()->active()->with(['guru.probes', 'probes'])->get();

        if ($activeCooks->isEmpty()) {
            $this->comment('No cooks in progress.');

            return self::SUCCESS;
        }

        $activeCooks
            ->groupBy('guru_id')
            ->each(fn (Collection $cooks) => $this->recordReadingsFor($cooks));

        return self::SUCCESS;
    }

    /** @param Collection<int, Cook> $cooks */
    private function recordReadingsFor(Collection $cooks): void
    {
        $guru = $cooks->first()->guru;

        $this->info("Reading CyberQ `{$guru->name}`...");

        try {
            $status = $guru->cyberQ()->status();
        } catch (CyberQUnreachable $exception) {
            $this->warn($exception->getMessage());

            $cooks->each(fn (Cook $cook) => $this->alerts->afterMissedPoll($cook));

            return;
        }

        $cooks->each(function (Cook $cook) use ($status) {
            $inUse = $cook->probesInUse()->pluck('identifier');

            $recorded = $cook->guru->probes
                ->filter(fn (Probe $probe) => ! $probe->isTemperature() || $inUse->contains($probe->identifier))
                ->filter(fn (Probe $probe) => $this->record($cook, $probe, $status))
                ->count();

            $this->comment("Recorded {$recorded} readings for cook `{$cook->name}`.");

            $this->liveActivities->update($cook);
            $this->alerts->afterReadings($cook);
        });
    }

    private function record(Cook $cook, Probe $probe, DeviceStatus $status): bool
    {
        $value = $status->integer($probe->identifier);

        if ($value === null) {
            return false;
        }

        Reading::create([
            'cook_id' => $cook->id,
            'probe_id' => $probe->id,
            'temperature' => $value,
            'set_point' => $status->integer(Probe::setPointKeyFor($probe->identifier)),
        ]);

        return true;
    }
}

<?php

namespace App\Console\Commands;

use App\Enums\Meat;
use App\Models\Cook;
use App\Models\Probe;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cooks:guess-meats {--dry-run : Show the guesses without saving them}')]
#[Description('Work out what finished cooks from before meats could be chosen were, from their names and notes, so they help forecast new cooks')]
class GuessCookMeatsCommand extends Command
{
    public function handle(): int
    {
        $cooks = Cook::query()
            ->whereNotNull('ended_at')
            ->with('probes')
            ->orderBy('started_at')
            ->get()
            ->reject(fn (Cook $cook) => $cook->probes->contains(fn (Probe $probe) => $probe->pivot->meat !== null));

        $guessed = 0;

        $cooks->each(function (Cook $cook) use (&$guessed) {
            $meat = Meat::guessFrom("{$cook->name} {$cook->description}");

            if (! $meat) {
                $this->warn("Cook {$cook->id} `{$cook->name}`: no guess, set it on the cook's edit page.");

                return;
            }

            $probesWithReadings = $this->probesWithReadings($cook);
            $foods = array_values(array_diff($probesWithReadings, [Probe::PIT]));

            if ($foods === []) {
                $this->warn("Cook {$cook->id} `{$cook->name}`: {$meat->getLabel()}, but no food probe has readings.");

                return;
            }

            $labels = implode(', ', array_map(fn (string $identifier) => Probe::LABELS[$identifier], $foods));
            $this->info("Cook {$cook->id} `{$cook->name}`: {$meat->getLabel()} on {$labels}.");
            $guessed++;

            if ($this->option('dry-run')) {
                return;
            }

            $cook->useProbes($probesWithReadings, collect($foods)->mapWithKeys(fn (string $identifier) => [$identifier => ['meat' => $meat]])->all());
        });

        $this->comment($this->option('dry-run') ?
            "Would set the meat on {$guessed} of {$cooks->count()} cooks." :
            "Set the meat on {$guessed} of {$cooks->count()} cooks.");

        return self::SUCCESS;
    }

    /** @return list<string> */
    private function probesWithReadings(Cook $cook): array
    {
        return $cook->readings()
            ->join('probes', 'probes.id', '=', 'readings.probe_id')
            ->whereIn('probes.identifier', Probe::TEMPERATURES)
            ->where('readings.temperature', '>', 0)
            ->distinct()
            ->pluck('probes.identifier')
            ->sortBy(fn (string $identifier) => array_search($identifier, Probe::TEMPERATURES, true))
            ->values()
            ->all();
    }
}

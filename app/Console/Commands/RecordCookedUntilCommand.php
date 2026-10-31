<?php

namespace App\Console\Commands;

use App\Models\Cook;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cooks:record-cooked-until')]
#[Description('Work out when each finished cook really stopped cooking, from its readings, for its duration and summary')]
class RecordCookedUntilCommand extends Command
{
    public function handle(): int
    {
        $cooks = Cook::query()->whereNotNull('ended_at')->orderBy('started_at')->get();

        $cooks->each(function (Cook $cook) {
            $this->info("Checking cook `{$cook->name}`...");

            $cook->recordCookedUntil();

            $this->line("  Ended {$cook->ended_at->format('D j M Y H:i')}, cooked until ".($cook->cooked_until?->format('D j M Y H:i') ?? 'no readings').", {$cook->durationForHumans()}");
        });

        $this->comment("Checked {$cooks->count()} cooks.");

        return self::SUCCESS;
    }
}

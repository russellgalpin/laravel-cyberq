<?php

namespace App\Console\Commands;

use App\Filament\Resources\Cooks\CookResource;
use App\Models\Cook;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('cooks:end-abandoned')]
#[Description('End cooks that have had no CyberQ readings for a while, as they were forgotten')]
class EndAbandonedCooksCommand extends Command
{
    public function handle(): int
    {
        $hours = (int) config('services.cyberq.abandoned_cook_hours');
        $cutoff = now()->subHours($hours);

        $abandonedCooks = Cook::query()
            ->active()
            ->withMax('readings', 'created_at')
            ->get()
            ->filter(fn (Cook $cook) => ($cook->lastReadingAt() ?? $cook->started_at)->lessThanOrEqualTo($cutoff));

        $abandonedCooks->each(function (Cook $cook) use ($hours) {
            $endedAt = $cook->lastReadingAt() ?? $cook->started_at;

            $this->info("Ending cook `{$cook->name}` at {$endedAt}...");

            $cook->endAutomatically($endedAt);

            Notification::make()
                ->title("{$cook->name} was ended automatically")
                ->body("There were no readings from the CyberQ for {$hours} hours, so the cook was ended at its last reading ({$endedAt->format('D j M H:i')}).")
                ->icon('heroicon-o-stop-circle')
                ->warning()
                ->actions([
                    Action::make('view')->url(CookResource::getUrl('view', ['record' => $cook])),
                ])
                ->sendToDatabase(User::all());
        });

        $this->comment("Ended {$abandonedCooks->count()} abandoned cooks.");

        return self::SUCCESS;
    }
}

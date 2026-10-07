<?php

namespace App\Support\Alerts;

use App\Enums\CookAlertKind;
use App\Models\Cook;
use App\Models\CookAlert;
use App\Models\Probe;
use App\Models\Reading;
use App\Support\CookSnapshot;
use App\Support\ProbeSnapshot;
use App\Support\TemperatureForecast;

/**
 * Decides when a cook needs someone's attention, after every poll of the CyberQ:
 *
 * - The pit has been more than pit_deviation_percent off its target for
 *   pit_alert_after_minutes, with a reminder pit_reminder_after_minutes later
 *   and a note when it recovers. Low alerts only count once the pit has reached
 *   its target range, so warming up (or raising the target) is not "too cold".
 * - A food probe is within food_nearly_ready_minutes of its target, and when it
 *   reaches it. Each is sent once per probe and target.
 * - The CyberQ has missed offline_after_missed_polls polls in a row, with a
 *   reminder and a note when it comes back.
 */
class CookAlertMonitor
{
    public function __construct(private readonly AlertNotifier $notifier) {}

    public function afterReadings(Cook $cook): void
    {
        $this->cyberQBackOnline($cook);

        $snapshot = new CookSnapshot($cook);

        if ($snapshot->isStale()) {
            return;
        }

        $this->checkPit($cook, $snapshot);

        $snapshot->probes()
            ->reject(fn (ProbeSnapshot $probe) => $probe->identifier === Probe::PIT)
            ->filter(fn (ProbeSnapshot $probe) => $probe->target !== null)
            ->each(fn (ProbeSnapshot $probe) => $this->checkFood($cook, $probe));
    }

    public function afterMissedPoll(Cook $cook): void
    {
        if (! $cook->readings()->exists()) {
            return;
        }

        $alert = $this->alert($cook, CookAlertKind::CyberQOffline);
        $alert->missed_polls++;
        $alert->condition_since ??= now();

        if (! $alert->notified_at && $alert->missed_polls >= $this->setting('offline_after_missed_polls')) {
            $this->notifier->send($cook, CookAlertKind::CyberQOffline, 'CyberQ offline',
                "{$cook->name}: no readings from the CyberQ for {$alert->missed_polls} minutes. Has it switched off?");
            $alert->notified_at = now();
        } elseif ($alert->notified_at && ! $alert->reminded_at && $alert->notified_at->lessThanOrEqualTo(now()->subMinutes($this->setting('offline_reminder_after_minutes')))) {
            $hours = (int) config('services.cyberq.abandoned_cook_hours');
            $this->notifier->send($cook, CookAlertKind::CyberQOffline, 'CyberQ still offline',
                "{$cook->name}: still no readings since {$alert->condition_since->format('H:i')}. The cook ends automatically after {$hours} hours without readings.");
            $alert->reminded_at = now();
        }

        $alert->save();
    }

    private function cyberQBackOnline(Cook $cook): void
    {
        $alert = CookAlert::query()->where('cook_id', $cook->id)->where('kind', CookAlertKind::CyberQOffline)->first();

        if (! $alert) {
            return;
        }

        if ($alert->notified_at) {
            $minutes = (int) $alert->condition_since->diffInMinutes(now());
            $this->notifier->send($cook, CookAlertKind::CyberQOffline, 'CyberQ back online',
                "{$cook->name}: readings resumed after {$minutes} minutes.");
        }

        $alert->reset();
        $alert->save();
    }

    private function checkPit(Cook $cook, CookSnapshot $snapshot): void
    {
        $pit = $snapshot->probe(Probe::PIT);

        if (! $pit || ! $pit->target) {
            return;
        }

        $band = $pit->target * $this->setting('pit_deviation_percent') / 100;
        $tooHot = $pit->temperature > $pit->target + $band;
        $tooCold = $pit->temperature < $pit->target - $band;

        $this->pitCondition($cook, CookAlertKind::PitHigh, $tooHot, $tooCold, $pit);
        $this->pitCondition($cook, CookAlertKind::PitLow, $tooCold && $this->pitHasReachedTarget($cook, $pit, $band), $tooHot, $pit);
    }

    private function pitCondition(Cook $cook, CookAlertKind $kind, bool $active, bool $offTheOtherWay, ProbeSnapshot $pit): void
    {
        $alert = $this->alert($cook, $kind);
        $hot = $kind === CookAlertKind::PitHigh;
        $degrees = self::degrees($pit->temperature);
        $target = self::degrees($pit->target);

        if (! $active) {
            if ($alert->notified_at && ! $offTheOtherWay) {
                $this->notifier->send($cook, $kind, 'Pit back on target', "{$cook->name}: pit is {$degrees} (target {$target}).");
            }

            $alert->reset();
            $alert->save();

            return;
        }

        $alert->condition_since ??= now();
        $difference = self::degrees(abs($pit->temperature - $pit->target), withUnit: false);

        if (! $alert->notified_at && $alert->condition_since->lessThanOrEqualTo(now()->subMinutes($this->setting('pit_alert_after_minutes')))) {
            $minutes = (int) $alert->condition_since->diffInMinutes(now());
            $this->notifier->send($cook, $kind, $hot ? 'Pit running hot' : 'Pit running cold',
                "{$cook->name}: pit is {$degrees}, {$difference}° ".($hot ? 'above' : 'below')." its {$target} target, for {$minutes} minutes.");
            $alert->notified_at = now();
        } elseif ($alert->notified_at && ! $alert->reminded_at && $alert->notified_at->lessThanOrEqualTo(now()->subMinutes($this->setting('pit_reminder_after_minutes')))) {
            $minutes = (int) $alert->condition_since->diffInMinutes(now());
            $this->notifier->send($cook, $kind, $hot ? 'Pit still hot' : 'Pit still cold',
                "{$cook->name}: pit is {$degrees} after {$minutes} minutes (target {$target}) and is {$this->pitTrend($cook, $hot)}.");
            $alert->reminded_at = now();
        }

        $alert->save();
    }

    private function pitHasReachedTarget(Cook $cook, ProbeSnapshot $pit, float $band): bool
    {
        return $cook->readings()
            ->whereRelation('probe', 'identifier', Probe::PIT)
            ->where('set_point', (int) round($pit->target * 10))
            ->where('temperature', '>=', (int) round(($pit->target - $band) * 10))
            ->exists();
    }

    private function pitTrend(Cook $cook, bool $hot): string
    {
        $recent = $cook->readings()
            ->whereRelation('probe', 'identifier', Probe::PIT)
            ->where('created_at', '>=', now()->subMinutes(10))
            ->orderBy('created_at')
            ->get()
            ->map(fn (Reading $reading) => ['at' => $reading->created_at, 'fahrenheit' => $reading->temperature_in_fahrenheit]);

        $risePerHour = $recent->count() >= 3 ? (new TemperatureForecast($recent))->risePerHour() : 0;

        if (abs($risePerHour) < 12) {
            return 'holding there';
        }

        $movingAway = $hot ? $risePerHour > 0 : $risePerHour < 0;

        return $movingAway ?
            ($hot ? 'still rising' : 'still falling') :
            ($hot ? 'coming down' : 'coming back up');
    }

    private function checkFood(Cook $cook, ProbeSnapshot $probe): void
    {
        $target = (int) round($probe->target * 10);
        $degrees = self::degrees($probe->temperature);

        if ($probe->reachedTarget()) {
            $this->onceForTarget($cook, CookAlertKind::FoodReady, $probe, $target, fn () => $this->notifier->send(
                $cook, CookAlertKind::FoodReady, "{$probe->label} is ready",
                "{$cook->name}: {$probe->label} is {$degrees}, its target. Time to take it off."
            ));

            return;
        }

        $minutesToGo = $probe->timeToTarget ? (int) round($probe->timeToTarget->totalMinutes) : null;

        if ($minutesToGo === null || $minutesToGo > $this->setting('food_nearly_ready_minutes')) {
            return;
        }

        $this->onceForTarget($cook, CookAlertKind::FoodNearlyReady, $probe, $target, fn () => $this->notifier->send(
            $cook, CookAlertKind::FoodNearlyReady, "{$probe->label} nearly ready",
            "{$cook->name}: {$probe->label} is {$degrees} and should reach ".self::degrees($probe->target)." in about {$minutesToGo} minutes, around ".now()->addMinutes($minutesToGo)->format('H:i').'.'
        ));
    }

    private function onceForTarget(Cook $cook, CookAlertKind $kind, ProbeSnapshot $probe, int $target, callable $send): void
    {
        $alert = $this->alert($cook, $kind, $probe->identifier);

        if ($alert->target !== $target) {
            $alert->reset();
            $alert->target = $target;
        }

        if (! $alert->notified_at) {
            $send();
            $alert->notified_at = now();
        }

        $alert->save();
    }

    private function alert(Cook $cook, CookAlertKind $kind, string $probe = ''): CookAlert
    {
        return CookAlert::query()->firstOrNew(['cook_id' => $cook->id, 'kind' => $kind, 'probe' => $probe]);
    }

    private function setting(string $key): int
    {
        return (int) config("services.cyberq.alerts.{$key}");
    }

    private static function degrees(float $fahrenheit, bool $withUnit = true): string
    {
        return round($fahrenheit).($withUnit ? '°F' : '');
    }
}

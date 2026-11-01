<?php

namespace App\Support\LiveActivities;

use App\Enums\LiveActivityTokenKind;
use App\Models\Cook;
use App\Models\LiveActivityToken;
use App\Services\Apns\ApnsClient;
use App\Services\Apns\ApnsResult;
use App\Support\CookSnapshot;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Keeps the iPhone app's Live Activities in step with cooks: starts one when a
 * cook begins, updates it after each reading and ends it with the cook.
 */
class LiveActivityBroadcaster
{
    /** Without an update for this long, iOS dims the Live Activity to show it is out of date. */
    public const int STALE_AFTER_MINUTES = 5;

    /** How long an ended cook's Live Activity stays on the Lock Screen. */
    public const int DISMISS_AFTER_MINUTES = 60;

    /** iOS ends a Live Activity after eight hours, so one retired this close to that ran out of time rather than being swiped away. */
    public const int RAN_OUT_OF_TIME_AFTER_MINUTES = 7 * 60 + 30;

    public function __construct(private readonly ApnsClient $apns) {}

    public function start(Cook $cook): void
    {
        $alreadyShowing = LiveActivityToken::query()
            ->where('kind', LiveActivityTokenKind::Update)
            ->where('cook_id', $cook->id)
            ->pluck('user_id');

        $tokens = LiveActivityToken::query()
            ->where('kind', LiveActivityTokenKind::Start)
            ->whereNotIn('user_id', $alreadyShowing);

        $this->sendStart($cook, $tokens, alert: true);
    }

    public function update(Cook $cook): void
    {
        $retired = $this->send($this->tokensFor($cook), fn () => [
            'aps' => [
                'timestamp' => now()->getTimestamp(),
                'event' => 'update',
                'content-state' => LiveActivityContent::state(new CookSnapshot($cook)),
                'stale-date' => now()->addMinutes(self::STALE_AFTER_MINUTES)->getTimestamp(),
            ],
        ]);

        $ranOutOfTime = $retired
            ->filter(fn (LiveActivityToken $token) => $token->created_at->lte(now()->subMinutes(self::RAN_OUT_OF_TIME_AFTER_MINUTES)))
            ->pluck('user_id');

        if ($ranOutOfTime->isEmpty()) {
            return;
        }

        $this->sendStart($cook, LiveActivityToken::query()->where('kind', LiveActivityTokenKind::Start)->whereIn('user_id', $ranOutOfTime), alert: false);
    }

    public function end(Cook $cook): void
    {
        $this->send($this->tokensFor($cook), fn () => [
            'aps' => [
                'timestamp' => now()->getTimestamp(),
                'event' => 'end',
                'content-state' => LiveActivityContent::state(new CookSnapshot($cook)),
                'dismissal-date' => now()->addMinutes(self::DISMISS_AFTER_MINUTES)->getTimestamp(),
            ],
        ]);

        $this->tokensFor($cook)->delete();
    }

    /** @param Builder<LiveActivityToken> $tokens */
    private function sendStart(Cook $cook, Builder $tokens, bool $alert): void
    {
        $this->send($tokens, fn () => [
            'aps' => array_filter([
                'timestamp' => now()->getTimestamp(),
                'event' => 'start',
                'attributes-type' => 'CookActivityAttributes',
                'attributes' => LiveActivityContent::attributes($cook),
                'content-state' => LiveActivityContent::state(new CookSnapshot($cook)),
                'stale-date' => now()->addMinutes(self::STALE_AFTER_MINUTES)->getTimestamp(),
                'alert' => $alert ? [
                    'title' => 'Cook started',
                    'body' => $cook->name,
                ] : null,
            ]),
        ]);
    }

    /** @return Builder<LiveActivityToken> */
    private function tokensFor(Cook $cook): Builder
    {
        return LiveActivityToken::query()
            ->where('kind', LiveActivityTokenKind::Update)
            ->where('cook_id', $cook->id);
    }

    /**
     * @param  Builder<LiveActivityToken>  $tokens
     * @param  callable(): array<string, mixed>  $payload
     * @return Collection<int, LiveActivityToken> the tokens Apple has retired, which are deleted
     */
    private function send(Builder $tokens, callable $payload): Collection
    {
        if (! $this->apns->isConfigured()) {
            return new Collection;
        }

        $tokens = $tokens->get();

        if ($tokens->isEmpty()) {
            return new Collection;
        }

        $body = $payload();

        return $tokens
            ->filter(fn (LiveActivityToken $token) => $this->apns->sendLiveActivity($token->token, $token->environment, $body) === ApnsResult::TokenInvalid)
            ->each(fn (LiveActivityToken $token) => $token->delete())
            ->values();
    }
}

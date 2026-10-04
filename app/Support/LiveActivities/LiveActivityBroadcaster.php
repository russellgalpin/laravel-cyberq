<?php

namespace App\Support\LiveActivities;

use App\Enums\LiveActivityTokenKind;
use App\Models\Cook;
use App\Models\LiveActivityToken;
use App\Services\Apns\ApnsClient;
use App\Services\Apns\ApnsResult;
use App\Support\CookSnapshot;
use Illuminate\Database\Eloquent\Builder;

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

        $this->send($tokens, fn () => [
            'aps' => [
                'timestamp' => now()->getTimestamp(),
                'event' => 'start',
                'attributes-type' => 'CookActivityAttributes',
                'attributes' => LiveActivityContent::attributes($cook),
                'content-state' => LiveActivityContent::state(new CookSnapshot($cook)),
                'stale-date' => now()->addMinutes(self::STALE_AFTER_MINUTES)->getTimestamp(),
                'alert' => [
                    'title' => 'Cook started',
                    'body' => $cook->name,
                ],
            ],
        ]);
    }

    public function update(Cook $cook): void
    {
        $this->send($this->tokensFor($cook), fn () => [
            'aps' => [
                'timestamp' => now()->getTimestamp(),
                'event' => 'update',
                'content-state' => LiveActivityContent::state(new CookSnapshot($cook)),
                'stale-date' => now()->addMinutes(self::STALE_AFTER_MINUTES)->getTimestamp(),
            ],
        ]);
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
     */
    private function send(Builder $tokens, callable $payload): void
    {
        if (! $this->apns->isConfigured()) {
            return;
        }

        $tokens = $tokens->get();

        if ($tokens->isEmpty()) {
            return;
        }

        $body = $payload();

        $tokens->each(function (LiveActivityToken $token) use ($body) {
            if ($this->apns->sendLiveActivity($token->token, $token->environment, $body) === ApnsResult::TokenInvalid) {
                $token->delete();
            }
        });
    }
}

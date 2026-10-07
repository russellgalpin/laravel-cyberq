<?php

namespace App\Support\Alerts;

use App\Enums\CookAlertKind;
use App\Models\Cook;
use App\Models\PushDevice;
use App\Services\Apns\ApnsClient;
use App\Services\Apns\ApnsResult;

/**
 * Sends a cook alert to every phone that has that kind of alert switched on.
 */
class AlertNotifier
{
    public function __construct(private readonly ApnsClient $apns) {}

    public function send(Cook $cook, CookAlertKind $kind, string $title, string $body): void
    {
        if (! $this->apns->isConfigured()) {
            return;
        }

        $payload = [
            'aps' => [
                'alert' => ['title' => $title, 'body' => $body],
                'sound' => 'default',
                'thread-id' => "cook-{$cook->id}",
                'interruption-level' => 'time-sensitive',
            ],
            'cook_id' => $cook->id,
            'kind' => $kind->value,
        ];

        PushDevice::query()
            ->where($kind->preference(), true)
            ->get()
            ->each(function (PushDevice $device) use ($payload) {
                if ($this->apns->sendAlert($device->token, $device->environment, $payload) === ApnsResult::TokenInvalid) {
                    $device->delete();
                }
            });
    }
}

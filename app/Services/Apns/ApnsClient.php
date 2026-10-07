<?php

namespace App\Services\Apns;

use App\Enums\ApnsEnvironment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends Live Activity pushes through Apple's push service, authenticating with
 * a token-based (.p8) APNs key.
 */
class ApnsClient
{
    /** Apple rejects provider tokens older than an hour, and refreshing too often is throttled. */
    private const int TOKEN_LIFETIME_MINUTES = 50;

    public function isConfigured(): bool
    {
        return filled(config('services.apns.key_id'))
            && filled(config('services.apns.team_id'))
            && is_readable((string) config('services.apns.private_key_path'));
    }

    /** @param array<string, mixed> $payload */
    public function sendLiveActivity(string $deviceToken, ApnsEnvironment $environment, array $payload, int $priority = 10): ApnsResult
    {
        return $this->send($deviceToken, $environment, $payload, 'liveactivity', config('services.apns.bundle_id').'.push-type.liveactivity', $priority);
    }

    /** @param array<string, mixed> $payload */
    public function sendAlert(string $deviceToken, ApnsEnvironment $environment, array $payload): ApnsResult
    {
        return $this->send($deviceToken, $environment, $payload, 'alert', config('services.apns.bundle_id'), 10);
    }

    /** @param array<string, mixed> $payload */
    private function send(string $deviceToken, ApnsEnvironment $environment, array $payload, string $pushType, string $topic, int $priority): ApnsResult
    {
        try {
            $response = Http::withOptions(['version' => 2.0])
                ->withToken($this->providerToken(), 'bearer')
                ->withHeaders([
                    'apns-push-type' => $pushType,
                    'apns-topic' => $topic,
                    'apns-priority' => (string) $priority,
                ])
                ->timeout(10)
                ->post("{$environment->host()}/3/device/{$deviceToken}", $payload);
        } catch (ConnectionException $exception) {
            Log::warning("APNs unreachable: {$exception->getMessage()}");

            return ApnsResult::Failed;
        }

        if ($response->successful()) {
            return ApnsResult::Sent;
        }

        $reason = $response->json('reason');

        if ($response->status() === 410 || in_array($reason, ['BadDeviceToken', 'Unregistered', 'DeviceTokenNotForTopic'], true)) {
            return ApnsResult::TokenInvalid;
        }

        Log::warning("APNs rejected a {$pushType} push: {$response->status()} {$reason}");

        return ApnsResult::Failed;
    }

    private function providerToken(): string
    {
        return Cache::remember('apns-provider-token', now()->addMinutes(self::TOKEN_LIFETIME_MINUTES), function () {
            $header = self::base64Url(json_encode(['alg' => 'ES256', 'kid' => config('services.apns.key_id')]));
            $claims = self::base64Url(json_encode(['iss' => config('services.apns.team_id'), 'iat' => now()->getTimestamp()]));

            $key = openssl_pkey_get_private(file_get_contents(config('services.apns.private_key_path')));

            if ($key === false || ! openssl_sign("{$header}.{$claims}", $derSignature, $key, OPENSSL_ALGO_SHA256)) {
                throw new RuntimeException('The APNs private key could not sign a token.');
            }

            return "{$header}.{$claims}.".self::base64Url(self::derToJose($derSignature));
        });
    }

    /**
     * OpenSSL gives an ASN.1 DER signature; JWTs want the two 32-byte integers side by side.
     */
    public static function derToJose(string $der): string
    {
        $offset = 2;

        if (ord($der[1]) & 0x80) {
            $offset += ord($der[1]) & 0x7F;
        }

        $integers = [];

        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $integer = ltrim(substr($der, $offset + 2, $length), "\x00");
            $integers[] = str_pad($integer, 32, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return implode('', $integers);
    }

    private static function base64Url(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

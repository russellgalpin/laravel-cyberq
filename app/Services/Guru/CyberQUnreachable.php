<?php

namespace App\Services\Guru;

use App\Models\Guru;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class CyberQUnreachable extends RuntimeException
{
    public static function for(Guru $guru, string $reason, ?Throwable $previous = null): self
    {
        return new self("Could not talk to the CyberQ `{$guru->name}` at {$guru->ip}: {$reason}", previous: $previous);
    }

    public function render(Request $request): ?JsonResponse
    {
        if (! $request->expectsJson()) {
            return null;
        }

        return response()->json(['message' => $this->getMessage(), 'cyberq_unreachable' => true], 503);
    }
}

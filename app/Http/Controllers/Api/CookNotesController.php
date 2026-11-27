<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cook;
use App\Models\CookNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class CookNotesController extends Controller
{
    public const int MAXIMUM_LENGTH = 2000;

    public function store(Request $request, Cook $cook): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['required', 'string', 'max:'.self::MAXIMUM_LENGTH],
            'noted_at' => ['nullable', 'date', 'before_or_equal:now'],
        ]);

        $note = $cook->notes()->create([
            'body' => trim($validated['body']),
            'noted_at' => $this->notedAt($cook, $validated['noted_at'] ?? null) ?? now(),
        ]);

        return response()->json(['data' => self::present($note)], 201);
    }

    public function update(Request $request, Cook $cook, CookNote $note): JsonResponse
    {
        $validated = $request->validate([
            'body' => ['sometimes', 'required', 'string', 'max:'.self::MAXIMUM_LENGTH],
            'noted_at' => ['sometimes', 'required', 'date', 'before_or_equal:now'],
        ]);

        $note->update(array_filter([
            'body' => isset($validated['body']) ? trim($validated['body']) : null,
            'noted_at' => $this->notedAt($cook, $validated['noted_at'] ?? null),
        ]));

        return response()->json(['data' => self::present($note)]);
    }

    public function destroy(Cook $cook, CookNote $note): Response
    {
        $note->delete();

        return response()->noContent();
    }

    private function notedAt(Cook $cook, ?string $notedAt): ?Carbon
    {
        if ($notedAt === null) {
            return null;
        }

        $at = Carbon::parse($notedAt);

        if ($cook->started_at && $at->lessThan($cook->started_at)) {
            throw ValidationException::withMessages(['noted_at' => 'A note cannot be from before the cook started.']);
        }

        return $at;
    }

    /** @return array{id: int, body: string, noted_at: string} */
    private static function present(CookNote $note): array
    {
        return [
            'id' => $note->id,
            'body' => $note->body,
            'noted_at' => $note->noted_at->toIso8601String(),
        ];
    }
}

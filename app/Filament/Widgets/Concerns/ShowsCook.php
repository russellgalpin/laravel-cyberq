<?php

namespace App\Filament\Widgets\Concerns;

use App\Models\Cook;
use Illuminate\Database\Eloquent\Model;

/**
 * Widgets on a cook's page are handed the cook as $record; on the dashboard
 * they fall back to the cook in progress.
 */
trait ShowsCook
{
    public ?Model $record = null;

    protected function cook(): ?Cook
    {
        if ($this->record instanceof Cook) {
            return $this->record;
        }

        return once(fn () => Cook::current());
    }

    protected function getPollingInterval(): ?string
    {
        return $this->cook()?->in_progress ? '60s' : null;
    }
}

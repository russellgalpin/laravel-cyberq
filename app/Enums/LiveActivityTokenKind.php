<?php

namespace App\Enums;

enum LiveActivityTokenKind: string
{
    /** Starts a Live Activity on the phone when a cook begins, even with the app closed. */
    case Start = 'start';

    /** Updates and ends one cook's Live Activity. */
    case Update = 'update';
}

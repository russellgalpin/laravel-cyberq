<?php

namespace App\Services\Apns;

enum ApnsResult
{
    case Sent;
    case TokenInvalid;
    case Failed;
}

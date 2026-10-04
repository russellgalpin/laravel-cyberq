<?php

namespace App\Enums;

enum ApnsEnvironment: string
{
    case Development = 'development';
    case Production = 'production';

    public function host(): string
    {
        return match ($this) {
            self::Development => 'https://api.sandbox.push.apple.com',
            self::Production => 'https://api.push.apple.com',
        };
    }
}

<?php

namespace App\Helpers;

use Config\Hash as HashConfig;

class Hash
{
    public static function make(string $password): string
    {
        $resolved = config(HashConfig::class)->resolve();

        return password_hash($password, $resolved['algo'], $resolved['options']);
    }

    public static function match(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }
}

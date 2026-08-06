<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

class SecureId
{
    public static function encode(int|string $id): string
    {
        return Crypt::encryptString((string) ((int) $id));
    }

    public static function decode(?string $token): int
    {
        if ($token === null || $token === '') {
            abort(403, 'Invalid request token.');
        }

        try {
            $id = (int) Crypt::decryptString($token);
        } catch (DecryptException $e) {
            abort(403, 'Invalid or tampered request token.');
        }

        if ($id <= 0) {
            abort(403, 'Invalid request token.');
        }

        return $id;
    }
}

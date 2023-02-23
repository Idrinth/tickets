<?php

namespace De\Idrinth\Tickets\Services;

class BlacklistHash
{
    public static function hash(string $mail, int $user): string
    {
        return md5($user . $mail . $_ENV['SYSTEM_BLACKLIST_SALT1'])
            . sha1($user . $mail . $_ENV['SYSTEM_BLACKLIST_SALT2']);
    }
}

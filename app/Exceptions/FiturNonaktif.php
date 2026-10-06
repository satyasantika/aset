<?php

namespace App\Exceptions;

use RuntimeException;

/** Dilempar Action bila toggle fitur (BR-22) dimatikan di pengaturan sistem. */
class FiturNonaktif extends RuntimeException
{
    public static function untuk(string $fitur): self
    {
        return new self("Fitur \"{$fitur}\" sedang dinonaktifkan oleh administrator.");
    }
}

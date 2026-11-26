<?php

namespace App\Support;

class StrHelper
{
    public static function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}

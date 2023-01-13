<?php

namespace App\Helper;

use Ramsey\Uuid\Uuid as UuidLib;

class Uuid
{
    public static function generateUuid()
    {
        return UuidLib::uuid4()->toString();
    }
}

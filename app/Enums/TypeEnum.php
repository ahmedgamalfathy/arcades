<?php

namespace App\Enums;

enum TypeEnum: int{

    case OFFLINE = 1;
    case ONLINE = 0;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

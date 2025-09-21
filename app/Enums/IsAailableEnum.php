<?php
namespace App\Enums;
enum IsAailableEnum:int{
    case AVAILABLE = 1 ;
    case UNAVAILABLE =0;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

}

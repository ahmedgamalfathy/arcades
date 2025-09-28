<?php
namespace App\Enums\Order;
enum OrderTypeEnum:int{//internal,external
    case INTERNAL = 0 ;
    case EXTERNAL =1;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

}

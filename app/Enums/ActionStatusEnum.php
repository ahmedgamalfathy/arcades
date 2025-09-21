<?php
namespace App\Enums;

enum ActionStatusEnum:int {
    case DEFAULT = 0;
     case CREATE = 1 ;
    case UPDATE =2;
    case DELETE =3;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

}

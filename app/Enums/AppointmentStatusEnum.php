<?php
namespace App\Enums;

enum AppointmentStatusEnum:int {
    case PENDING = 0;
     case APPROVED = 1 ;
    case CANCELLED =2;


    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

}

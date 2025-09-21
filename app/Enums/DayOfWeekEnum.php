<?php
namespace App\Enums;
enum DayOfWeekEnum:int{
    case STARDAY = 6;
    case SUNDAY = 0;
    case MONDAY = 1;
    case TUESDAY = 2;
    case WEDNESDAY = 3;
    case THURSDAY = 4;
    case FRIDAY = 5;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

}

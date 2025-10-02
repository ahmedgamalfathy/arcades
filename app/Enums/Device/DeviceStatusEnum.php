<?php
namespace App\Enums\Device;
enum DeviceStatusEnum:int{
    case UNAVAILABLE = 0;
    case AVAILABLE = 1;
    case MAINTENANCES =2;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

}

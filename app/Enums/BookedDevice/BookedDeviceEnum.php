<?php
namespace App\Enums\BookedDevice;
//'individual', 'group'
enum BookedDeviceEnum:int{
    case FINISHED=0;
    case ACTIVE=1;
    case PAUSED=2;
    case RESUME=3;
    public function values()
    {
        return array_column(self::cases(),'value');
    }
}

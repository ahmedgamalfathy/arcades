<?php
namespace App\Enums\SessionDevice;
//'individual', 'group'
enum SessionDeviceEnum:int{
    case INDIVIDUAL=0;
    case GROUP=1;
    public function values()
    {
        return array_column(self::cases(),'value');
    }
}

<?php
namespace App\Enums\Media;

enum MediaTypeEnum:int{
    case VIDEO=1;
    case PHOTO=0;
    public function values()
    {
        return array_column(self::cases(),'value');
    }
}

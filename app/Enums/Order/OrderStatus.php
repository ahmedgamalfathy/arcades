<?php
namespace App\Enums\Order;
enum OrderStatus :int{
    case CANCEL = 0;
    case CONFIRM = 1;
//canel =4
    public static function values(){
        return  array_column(self::cases(), 'value');
    }

}

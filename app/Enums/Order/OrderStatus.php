<?php
namespace App\Enums\Order;
enum OrderStatus :int{
    case CONFIRMED = 1;
    case PENDING = 2;
    case DELIVERED = 3;
    case CANCELED = 4;
//canel =4
    public static function values(){
        return  array_column(self::cases(), 'value');
    }

}

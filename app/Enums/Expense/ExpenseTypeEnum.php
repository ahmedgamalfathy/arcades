<?php
namespace App\Enums\Expense;;
enum ExpenseTypeEnum:int{//internal,external
    case INTERNAL = 0 ;
    case EXTERNAL =1;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

}

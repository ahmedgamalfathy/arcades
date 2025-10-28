<?php
namespace App\Services\Select;

use App\Models\Daily\Daily;

class DailySelectService
{
//dailynow
    public function getDailyNow()
    {
        return Daily::on('tenant')->where('end_date_time', null)->get(['id as value', 'start_date_time as label']);
    }

}

<?php
namespace App\Services\Select;

use App\Models\Daily\Daily;
use App\Models\Timer\SessionDevice\SessionDevice;

class SessionSelectService
{
//dailynow
    public function getSessionGroup()
    {
        return SessionDevice::on('tenant')->where('type',1 )->get(['id as value', 'name as label']);
    }

}

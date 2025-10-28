<?php
namespace App\Services\Select;

use App\Models\Setting\Param\Param;

class ParameterSelectService
{

    public function getAllParameters()
    {
        return Param::on('tenant')->where('parameter_order',1)->get(['id as value', 'type as label']);
    }
}

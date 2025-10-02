<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

class UniqueDeviceTimeName implements ValidationRule
{
    protected int $deviceTypeId;
    protected ?int $ignoreId;

    public function __construct(int $deviceTypeId, ?int $ignoreId = null)
    {
        $this->deviceTypeId = $deviceTypeId;
        $this->ignoreId = $ignoreId;
    }

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $query = DB::table('device_times')
            ->where('device_type_id', $this->deviceTypeId)
            ->where('name', $value);

        if ($this->ignoreId) {
            $query->where('id', '!=', $this->ignoreId);
        }

        if ($query->exists()) {
            $fail("The name '$value' already exists for this device type.");
        }
    }
}

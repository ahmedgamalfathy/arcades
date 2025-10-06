<?php

namespace App\Trait;

trait UsesTenantConnection
{
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        if (config('database.default') === 'tenant') {
            $this->setConnection('tenant');
        }
    }
}

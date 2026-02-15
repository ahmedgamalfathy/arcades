<?php

namespace App\Models\Notification;

use Illuminate\Notifications\DatabaseNotification;

class Notification extends DatabaseNotification
{
    protected $connection = 'tenant';
}

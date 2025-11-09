<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('booked-device-expire-time', function ($user) {
    return true;
});
Broadcast::channel('booked-devices', function ($user) {
    return true;
});
    
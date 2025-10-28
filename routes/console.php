<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
  
// Schedule::command('devices:notify-ending')->everyMinute();
Schedule::command('notifications:check-booked-devices')
    ->everyMinute()
    ->withoutOverlapping() // منع التشغيل المتزامن
    ->runInBackground(); // تشغيل في الخلفية
    
Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

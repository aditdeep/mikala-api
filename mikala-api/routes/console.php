<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\SendBillingReminderJob;

// Scheduler
Schedule::job(new SendBillingReminderJob)->dailyAt('08:00');
Schedule::command('artikel:publish-scheduled')->everyMinute()->withoutOverlapping();

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

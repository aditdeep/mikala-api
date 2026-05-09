<?php

namespace App\Jobs;

use App\Services\BillingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendBillingReminderJob implements ShouldQueue
{
    use Queueable;

    /**
     * Execute the job.
     */
    public function handle(BillingService $billingService): void
    {
        $billingService->sendReminders();
    }
}

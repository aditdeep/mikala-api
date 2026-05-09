<?php

namespace App\Jobs;

use App\Services\ReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateReportJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $reportType,
        public string $startDate,
        public string $endDate
    ) {}

    /**
     * Execute the job.
     */
    public function handle(ReportService $reportService): void
    {
        $report = match($this->reportType) {
            'financial' => $reportService->financialReport($this->startDate, $this->endDate),
            'order' => $reportService->orderReport($this->startDate, $this->endDate),
            'mitra' => $reportService->mitraReport(),
            'klien' => $reportService->klienReport(),
            default => null,
        };

        // TODO: Save report to storage or send via email
        // Storage::put("reports/{$this->reportType}-" . date('Y-m-d') . ".json", json_encode($report));
    }
}

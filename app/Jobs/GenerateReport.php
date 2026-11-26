<?php

namespace App\Jobs;

use App\Models\Report;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\SkipIfBatchCancelled;
use Throwable;

class GenerateReport implements ShouldQueue
{
    use Batchable, Queueable;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(public int $reportId) {}

    public function middleware(): array
    {
        return [new SkipIfBatchCancelled];
    }

    public function handle(): void
    {
        $report = Report::findOrFail($this->reportId);
        $active = Report::whereKey($report->id)->whereNotIn('status', ['ready', 'cancelled']);
        if (! (clone $active)->update(['status' => 'processing'])) {
            return;
        }

        $scores = $report->scores;
        $result = [
            'count' => count($scores),
            'average' => round(array_sum($scores) / count($scores), 2),
            'highest' => max($scores),
            'lowest' => min($scores),
        ];
        // A cancellation may have committed while the grades were being calculated.
        $active->update(['status' => 'ready', 'result' => json_encode($result, JSON_THROW_ON_ERROR)]);
    }

    public function failed(?Throwable $exception): void
    {
        Report::whereKey($this->reportId)->whereNotIn('status', ['ready', 'cancelled'])
            ->update(['status' => 'failed']);
    }
}

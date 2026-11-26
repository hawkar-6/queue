<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportBatch extends Model
{
    public $incrementing = false;
    public $timestamps = false;
    protected $keyType = 'string';
    protected $fillable = ['id', 'user_id'];

    public function reports(): HasMany
    {
        return $this->hasMany(Report::class, 'batch_id');
    }

    public function summary(): array
    {
        $reports = $this->reports()->orderBy('id')->get();
        $counts = $reports->countBy('status');
        $total = $reports->count();
        $ready = $counts->get('ready', 0);
        $status = match (true) {
            $counts->has('cancelled') => 'cancelled',
            $ready === $total => 'ready',
            $counts->get('queued', 0) === $total => 'queued',
            $counts->has('queued') || $counts->has('processing') => 'processing',
            default => 'failed',
        };

        return [
            'batch_id' => $this->id,
            'status' => $status,
            'total' => $total,
            'completed' => $ready,
            // A skipped or failed job is not a completed grade report.
            'progress' => (int) round(100 * $ready / max(1, $total)),
            'reports' => $reports->each->makeVisible('class_name'),
        ];
    }
}

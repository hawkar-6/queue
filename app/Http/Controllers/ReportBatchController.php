<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReport;
use App\Models\Report;
use App\Models\ReportBatch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

/** @group Class report batches */
class ReportBatchController extends Controller
{
    /**
     * Submit class reports
     * Each class becomes one independent job on the class-reports queue.
     * @bodyParam classes object[] required Between 1 and 20 classes. Example: [{"name":"Class A","scores":[60,80,100]}]
     * @bodyParam classes[].name string required Unique class name. Example: Class A
     * @bodyParam classes[].scores integer[] required Scores between 0 and 100. Example: [60,80,100]
     * @response 202 {"batch_id":"890b38a0-301c-4959-9a28-3592c39aec40","status":"queued","total":1,"completed":0,"progress":0,"reports":[{"id":1,"class_name":"Class A","status":"queued","result":null}]}
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'classes' => 'required|array|list|min:1|max:20',
            'classes.*' => 'required|array:name,scores',
            'classes.*.name' => 'required|string|max:80|distinct:ignore_case',
            'classes.*.scores' => 'required|array|list|min:1|max:1000',
            'classes.*.scores.*' => 'required|integer|between:0,100',
        ]);

        $summary = DB::transaction(function () use ($data, $request) {
            $jobs = [];
            $ids = [];
            foreach ($data['classes'] as $class) {
                $report = Report::create([
                    'user_id' => $request->user()->id,
                    'class_name' => $class['name'],
                    'scores' => array_map('intval', $class['scores']),
                    'status' => 'queued',
                ]);
                $ids[] = $report->id;
                $jobs[] = new GenerateReport($report->id);
            }

            $batch = Bus::batch($jobs)->name('Class grade reports')->allowFailures()
                ->onConnection('database')->onQueue('class-reports')->dispatch();
            $group = ReportBatch::create(['id' => $batch->id, 'user_id' => $request->user()->id]);
            Report::whereKey($ids)->update(['batch_id' => $group->id]);
            return $group->summary();
        });
        return response()->json($summary, 202);
    }

    /**
     * Read progress and one result per class
     * Progress counts ready reports, including after cancellation.
     * @urlParam batch_id string required Batch UUID. Example: 890b38a0-301c-4959-9a28-3592c39aec40
     * @response 200 {"batch_id":"890b38a0-301c-4959-9a28-3592c39aec40","status":"ready","total":1,"completed":1,"progress":100,"reports":[{"id":1,"class_name":"Class A","status":"ready","result":{"count":3,"average":80,"highest":100,"lowest":60}}]}
     * @response 403 {"message":"This action is unauthorized."}
     */
    public function show(ReportBatch $batch)
    {
        return response()->json($batch->summary());
    }

    /**
     * Cancel outstanding reports
     * Ready results are retained. Repeating a cancellation is safe.
     * @urlParam batch_id string required Batch UUID. Example: 890b38a0-301c-4959-9a28-3592c39aec40
     * @response 200 {"batch_id":"890b38a0-301c-4959-9a28-3592c39aec40","status":"cancelled","total":1,"completed":0,"progress":0,"reports":[{"id":1,"class_name":"Class A","status":"cancelled","result":null}]}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 409 {"message":"This batch has already finished."}
     */
    public function cancel(ReportBatch $batch)
    {
        return DB::transaction(function () use ($batch) {
            $changed = $batch->reports()->whereIn('status', ['queued', 'processing'])
                ->update(['status' => 'cancelled']);
            if ($changed) {
                Bus::findBatch($batch->id)?->cancel();
            } else {
                abort_unless($batch->reports()->where('status', 'cancelled')->exists(), 409,
                    'This batch has already finished.');
            }
            return response()->json($batch->summary());
        });
    }
}

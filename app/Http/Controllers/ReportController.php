<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateReport;
use App\Models\Report;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** @group Reports */
class ReportController extends Controller
{
    /**
     * Queue a grade report
     * @bodyParam scores integer[] required List of 1 to 1000 scores from 0 to 100. Example: [60,80,100]
     * @response 202 {"id":1,"status":"queued","result":null}
     * @response 422 {"message":"The scores field is required.","errors":{"scores":["The scores field is required."]}}
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'scores' => ['required', 'array', 'list', 'min:1', 'max:1000'],
            'scores.*' => ['required', 'integer', 'between:0,100'],
        ]);
        $report = DB::transaction(function () use ($request, $data) {
            $report = Report::create([
                'user_id' => $request->user()->id,
                'scores' => array_map('intval', $data['scores']),
                'status' => 'queued',
                'result' => null,
            ]);
            // Both the report and the database queue entry use the same connection.
            GenerateReport::dispatch($report->id)->onConnection('database')->beforeCommit();
            return $report;
        });
        return response()->json($report, 202);
    }

    /**
     * Read your report
     * @urlParam report_id integer required Report ID. Example: 1
     * @response 200 {"id":1,"status":"ready","result":{"count":3,"average":80,"highest":100,"lowest":60}}
     * @response 401 {"message":"Unauthenticated."}
     * @response 403 {"message":"This action is unauthorized."}
     * @response 404 {"message":"Resource not found."}
     */
    public function show(Report $report)
    {
        return response()->json($report);
    }
}

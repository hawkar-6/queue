<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ReportBatchTest extends TestCase
{
    use RefreshDatabase;

    private function submit(): string
    {
        $classes = array_map(fn ($name) => ['name' => $name, 'scores' => [60,80,100]], ['A','B','C','D']);
        $id = $this->postJson('/api/report-batches', ['classes' => $classes])->assertAccepted()
            ->assertJsonPath('progress', 0)->assertJsonPath('total', 4)->json('batch_id');
        $this->assertSame(4, Bus::findBatch($id)->totalJobs);
        return "/api/report-batches/$id";
    }

    private function runWorker(bool $once = false): void
    {
        $this->artisan('queue:work', [
            'connection' => 'database', '--queue' => 'class-reports',
            $once ? '--once' : '--stop-when-empty' => true, '--sleep' => 0,
        ])->assertExitCode(0);
    }

    public function test_live_progress_and_results(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $url = $this->submit();
        $this->runWorker(true);
        $this->getJson($url)->assertOk()->assertJsonPath('progress', 25)->assertJsonPath('completed', 1);
        $this->runWorker();
        $this->getJson($url)->assertOk()->assertJsonPath('progress', 100)->assertJsonPath('status', 'ready')
            ->assertJsonPath('reports.3.class_name', 'D')->assertJsonPath('reports.3.result.average', 80);
        $this->postJson("$url/cancel")->assertConflict();
    }

    public function test_only_owner_can_cancel_and_ready_results_survive_queue_drain(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner, 'sanctum');
        $url = $this->submit();
        $this->runWorker(true);
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->getJson($url)->assertForbidden();
        $this->postJson("$url/cancel")->assertForbidden();
        $this->actingAs($owner, 'sanctum');
        $this->postJson("$url/cancel")->assertOk()->assertJsonPath('status', 'cancelled');
        $this->assertTrue(Bus::findBatch(basename($url))->cancelled());
        $this->runWorker();
        $this->postJson("$url/cancel")->assertOk();
        $this->getJson($url)->assertJsonPath('progress', 25)->assertJsonPath('reports.0.status', 'ready')
            ->assertJsonPath('reports.1.status', 'cancelled')->assertJsonPath('reports.3.result', null);
        $this->assertDatabaseCount('jobs', 0);
    }

    public function test_invalid_submission_creates_no_batch(): void
    {
        $this->postJson('/api/report-batches')->assertUnauthorized();
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->postJson('/api/report-batches', ['classes' => [
            ['name' => 'A', 'scores' => [30]], ['name' => 'B', 'scores' => [110]],
        ]])->assertUnprocessable()->assertJsonValidationErrors('classes.1.scores.0');
        $this->assertDatabaseCount('report_batches', 0);
        $this->assertDatabaseCount('reports', 0);
        $this->assertDatabaseCount('jobs', 0);
    }
}

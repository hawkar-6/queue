<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_generates_only_the_owners_report(): void
    {
        $this->get('/api/reports/1')->assertUnauthorized();
        $owner = User::factory()->create();
        $this->actingAs($owner, 'sanctum');
        $this->postJson('/api/reports', ['scores' => [101]])->assertUnprocessable();
        $id = $this->postJson('/api/reports', ['scores' => [60, 80, 100]])
            ->assertAccepted()->assertJsonPath('status', 'queued')->json('id');
        $this->assertDatabaseCount('jobs', 1);

        $this->artisan('queue:work', ['connection' => 'database', '--once' => true])->assertExitCode(0);
        $this->getJson("/api/reports/$id")->assertOk()->assertJsonPath('status', 'ready')
            ->assertJsonPath('result.average', 80)->assertJsonPath('result.highest', 100);
        $this->actingAs(User::factory()->create(), 'sanctum');
        $this->getJson("/api/reports/$id")->assertForbidden();
        $this->getJson('/api/reports/999999')->assertNotFound();
    }

    public function test_login_and_revoke_token(): void
    {
        $data = ['name' => 'Sara', 'email' => 'sara@example.test', 'password' => 'long-example-pass'];
        $this->postJson('/api/register', $data)->assertCreated();
        $this->postJson('/api/login', array_merge($data, ['password' => 'wrong']))->assertUnauthorized();
        $token = $this->postJson('/api/login', $data)->assertOk()->json('token');
        $this->withToken($token)->postJson('/api/logout')->assertNoContent();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->postJson('/api/reports', ['scores' => [10]])->assertUnauthorized();
    }
}

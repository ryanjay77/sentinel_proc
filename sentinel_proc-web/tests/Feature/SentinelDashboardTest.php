<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SentinelDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create([
            'role' => UserRole::Admin,
        ]);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_uses_live_snapshot_data_when_available(): void
    {
        $snapshotPath = base_path('../monitoring_agent/live_snapshot.json');
        $directory = dirname($snapshotPath);

        if (! is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        file_put_contents($snapshotPath, json_encode([
            'stats' => [
                ['label' => 'Running Processes', 'value' => '42', 'trend' => '+4%', 'tone' => 'primary'],
                ['label' => 'High Risk', 'value' => '1', 'trend' => '+1', 'tone' => 'danger'],
            ],
            'processes' => [[
                'pid' => 9999,
                'name' => 'custom-process',
                'user' => 'live-user',
                'cpu' => '15.2%',
                'memory' => '56 MB',
                'status' => 'Normal',
            ]],
            'alerts' => [[
                'severity' => 'HIGH',
                'title' => 'Real-time detection from snapshot',
                'time' => 'just now',
                'source' => 'custom-process',
            ]],
        ], JSON_PRETTY_PRINT));

        $response = $this->actingAs($this->admin())->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Real-time Monitoring');
        $response->assertSee('custom-process');
    }

    public function test_dashboard_refresh_snapshot_button_is_available_for_admin(): void
    {
        $response = $this->actingAs($this->admin())->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Refresh Snapshot');

        $refreshResponse = $this->actingAs($this->admin())->post('/monitor/refresh');

        $refreshResponse->assertOk();
        $refreshResponse->assertJsonPath('ok', true);
    }

    public function test_viewer_cannot_refresh_snapshot(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)
            ->post('/monitor/refresh')
            ->assertForbidden();
    }
}

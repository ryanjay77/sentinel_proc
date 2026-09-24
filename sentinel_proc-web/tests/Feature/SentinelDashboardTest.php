<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SentinelDashboardTest extends TestCase
{
    use RefreshDatabase;

    private ?string $snapshotBackup = null;

    protected function setUp(): void
    {
        parent::setUp();

        // The dashboard reads this real file, so a test that writes a fixture into
        // it must put the previous contents back. Without this, running the suite
        // replaces live agent output with placeholder data until the next scan.
        if (is_file($this->snapshotPath())) {
            $this->snapshotBackup = file_get_contents($this->snapshotPath());
        }
    }

    protected function tearDown(): void
    {
        // Runs before parent::tearDown() so base_path() still resolves.
        if ($this->snapshotBackup !== null) {
            file_put_contents($this->snapshotPath(), $this->snapshotBackup);
        } elseif (is_file($this->snapshotPath())) {
            unlink($this->snapshotPath());
        }

        parent::tearDown();
    }

    private function snapshotPath(): string
    {
        return base_path('../monitoring_agent/live_snapshot.json');
    }

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
        $snapshotPath = $this->snapshotPath();
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

    public function test_dashboard_renders_analytics_aggregates(): void
    {
        $snapshotId = DB::table('monitoring_snapshots')->insertGetId([
            'snapshot_timestamp' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('processes')->insert([
            [
                'monitoring_snapshot_id' => $snapshotId,
                'pid' => 111, 'name' => 'noisy-proc', 'risk_level' => 'high',
                'cpu_percent' => 42.5, 'memory_mb' => 512.25,
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'monitoring_snapshot_id' => $snapshotId,
                'pid' => 222, 'name' => 'quiet-proc', 'risk_level' => 'low',
                'cpu_percent' => 1.0, 'memory_mb' => 64.5,
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);

        DB::table('alerts')->insert([
            'monitoring_snapshot_id' => $snapshotId,
            'alert_type' => 'risk_score',
            'severity' => 'high',
            'message' => 'Seeded analytics alert',
            'created_at' => now(),
        ]);

        DB::table('processes_seen')->insert([
            'file_hash' => str_repeat('a', 64),
            'process_name' => 'freshly-discovered',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($this->admin())->get('/dashboard');

        $response->assertOk();
        $response->assertSee('Process Inventory by Risk');
        $response->assertSee('2 tracked');
        $response->assertSee('Alert Log by Severity');
        $response->assertSee('New Processes Discovered per Day');
        $response->assertSee('Top 5 Memory Consumers');
        $response->assertSee('riskLevelChart', false);
        $response->assertSee('memoryConsumersChart', false);
    }
}

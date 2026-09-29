<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Agent;
use App\Models\Alert;
use App\Models\MonitoringSnapshot;
use App\Models\Process;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonitoringApiTest extends TestCase
{
    use RefreshDatabase;

    private function agentToken(string $name = 'test-agent', array $abilities = ['monitoring:write', 'monitoring:context']): string
    {
        $user = User::factory()->create(['role' => UserRole::Admin]);

        return $user->createToken($name, $abilities)->plainTextToken;
    }

    private function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer ' . $token];
    }

    public function test_guests_cannot_post_snapshots_or_context(): void
    {
        $this->postJson('/api/monitoring/snapshot', ['snapshot' => '{}'])->assertUnauthorized();
        $this->postJson('/api/monitoring/context', ['hashes' => []])->assertUnauthorized();
    }

    public function test_health_is_public_and_returns_only_ok(): void
    {
        $this->getJson('/api/health')->assertOk()->assertExactJson(['ok' => true]);
    }

    public function test_wrong_and_revoked_tokens_cannot_access_the_api(): void
    {
        $this->postJson('/api/monitoring/context', ['hashes' => []], $this->bearer('not-a-valid-token'))
            ->assertUnauthorized();

        $user = User::factory()->create(['role' => UserRole::Admin]);
        $accessToken = $user->createToken('revoked-agent', ['monitoring:context']);
        $user->tokens()->whereKey($accessToken->accessToken->getKey())->delete();

        $this->postJson('/api/monitoring/context', ['hashes' => []], $this->bearer($accessToken->plainTextToken))
            ->assertUnauthorized();
    }

    public function test_ingest_is_rate_limited_per_token(): void
    {
        config([
            'monitoring_api.rate_limit_per_token' => 1,
            'monitoring_api.rate_limit_per_ip' => 5,
        ]);

        $token = $this->agentToken();
        $payload = ['hashes' => [str_repeat('a', 64)]];
        $this->postJson('/api/monitoring/context', $payload, $this->bearer($token))->assertOk();
        $this->postJson('/api/monitoring/context', $payload, $this->bearer($token))->assertTooManyRequests();

        $otherToken = $this->agentToken('other-agent');
        $this->postJson('/api/monitoring/context', $payload, $this->bearer($otherToken))->assertOk();
    }

    public function test_ingest_is_rate_limited_per_ip(): void
    {
        config([
            'monitoring_api.rate_limit_per_token' => 5,
            'monitoring_api.rate_limit_per_ip' => 1,
        ]);

        $payload = ['hashes' => [str_repeat('a', 64)]];
        $this->postJson('/api/monitoring/context', $payload, $this->bearer($this->agentToken()))
            ->assertOk();
        $this->postJson('/api/monitoring/context', $payload, $this->bearer($this->agentToken('other-agent')))
            ->assertTooManyRequests();
    }

    public function test_context_returns_known_hashes_vt_cache_and_lowercased_lists(): void
    {
        $hashKnown = str_repeat('a', 64);
        $hashVt = str_repeat('b', 64);
        $hashNew = str_repeat('c', 64);

        DB::table('processes_seen')->insert([
            ['file_hash' => $hashKnown, 'process_name' => 'known.exe', 'file_path' => 'C:/Windows/known.exe',
             'vt_checked_at' => null, 'vt_malicious_count' => null, 'vt_total_engines' => null, 'created_at' => now()],
            ['file_hash' => $hashVt, 'process_name' => 'vt.exe', 'file_path' => 'C:/Windows/vt.exe',
             'vt_checked_at' => now(), 'vt_malicious_count' => 3, 'vt_total_engines' => 70, 'created_at' => now()],
        ]);
        DB::table('process_lists')->insert([
            ['type' => 'whitelist', 'match_by' => 'name', 'value' => 'NotePad.EXE', 'created_at' => now(), 'updated_at' => now()],
            ['type' => 'blacklist', 'match_by' => 'name', 'value' => 'EVIL.exe', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $response = $this->postJson(
            '/api/monitoring/context',
            ['hashes' => [$hashNew, $hashKnown, $hashVt, $hashVt]],
            $this->bearer($this->agentToken())
        )->assertOk()->assertJsonPath('ok', true);

        $known = $response->json('known_hashes');
        sort($known);
        $this->assertEquals([$hashKnown, $hashVt], $known);

        $this->assertEquals(['malicious' => 3, 'total' => 70], $response->json('vt_cache.'.$hashVt));
        $this->assertArrayNotHasKey($hashKnown, $response->json('vt_cache'));

        $this->assertEquals(
            [['match_by' => 'name', 'value' => 'notepad.exe']],
            $response->json('process_lists.whitelist')
        );
        $this->assertEquals(
            [['match_by' => 'name', 'value' => 'evil.exe']],
            $response->json('process_lists.blacklist')
        );
    }

    public function test_context_requires_the_monitoring_context_ability(): void
    {
        $token = $this->agentToken(abilities: ['monitoring:write']);

        $this->postJson(
            '/api/monitoring/context',
            ['hashes' => [str_repeat('a', 64)]],
            $this->bearer($token)
        )->assertForbidden();
    }

    public function test_api_mode_snapshot_creates_processes_activity_alerts_and_registries(): void
    {
        $hashNew = str_repeat('a', 64);
        $hashVt = str_repeat('b', 64);
        $scanUuid = (string) Str::uuid();

        // The snapshot JSON stays the dashboard-shaped summary the local
        // agent writes — including its display-only alerts.
        $dashboardSummary = [
            'stats' => [['label' => 'Running Processes', 'value' => '2', 'trend' => '', 'tone' => 'primary']],
            'processes' => [
                ['pid' => 101, 'name' => 'chrome.exe', 'user' => 'CORP\\tester', 'cpu' => '5.0%', 'memory' => '101 MB', 'status' => 'Medium Risk'],
            ],
            'alerts' => [
                ['severity' => 'MEDIUM', 'title' => 'chrome.exe triggered risk scoring rules', 'time' => 'just now', 'source' => 'C:/Program Files/chrome.exe'],
            ],
            'generated_at' => now()->toIso8601String(),
        ];

        $response = $this->postJson('/api/monitoring/snapshot', [
            'snapshot' => json_encode($dashboardSummary),
            'process_count' => 2,
            'scan_uuid' => $scanUuid,
            'hostname' => 'laptop-42',
            'processes' => [
                [
                    'pid' => 101, 'name' => 'chrome.exe', 'path' => 'C:/Program Files/chrome.exe',
                    'cpu_percent' => 5.0, 'memory_mb' => 100.5, 'status' => 'CORP\\tester',
                    'hash' => $hashNew, 'first_seen' => true, 'risk_level' => 'medium',
                    'score' => 25, 'reasons' => ['first_seen'], 'virus_total_data' => null,
                ],
                [
                    'pid' => 102, 'name' => 'evil.exe', 'path' => 'C:/temp/evil.exe',
                    'cpu_percent' => 1.0, 'memory_mb' => 10.0, 'status' => 'CORP\\tester',
                    'hash' => $hashVt, 'first_seen' => true, 'risk_level' => 'high',
                    'score' => 75, 'reasons' => ['first_seen', 'virustotal malicious'],
                    'virus_total_data' => ['malicious' => 6, 'total' => 70],
                ],
            ],
        ], $this->bearer($this->agentToken('laptop-42')));

        $response->assertCreated()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('agent', 'laptop-42')
            ->assertJsonPath('duplicate', false)
            ->assertJsonPath('scan_uuid', $scanUuid);

        $snapshotId = $response->json('snapshot_id');
        $this->assertSame('laptop-42', MonitoringSnapshot::findOrFail($snapshotId)->hostname);

        $chrome = Process::where('monitoring_snapshot_id', $snapshotId)->where('name', 'chrome.exe')->first();
        $evil = Process::where('monitoring_snapshot_id', $snapshotId)->where('name', 'evil.exe')->first();
        $this->assertNotNull($chrome);
        $this->assertNotNull($evil);
        $this->assertSame('laptop-42', $chrome->hostname);
        $this->assertSame('CORP\\tester', $chrome->status);
        $this->assertSame(1, (int) $chrome->first_seen);
        $this->assertEquals(['malicious' => 6, 'total' => 70], $evil->virus_total_data);

        // Activity logs classify events the same way the local agent does.
        $events = ActivityLog::where('monitoring_snapshot_id', $snapshotId)->pluck('event_type', 'process_name');
        $this->assertSame('first_seen', $events['chrome.exe']);
        $this->assertSame('vt_flagged', $events['evil.exe']);

        // Alerts are derived from the scored processes — the snapshot JSON's
        // display alerts must NOT become Alert rows.
        $alerts = Alert::where('monitoring_snapshot_id', $snapshotId)->get();
        $this->assertCount(2, $alerts);
        $this->assertSame(['laptop-42', 'laptop-42'], $alerts->pluck('hostname')->all());

        $chromeAlert = $alerts->firstWhere('severity', 'medium');
        $this->assertSame('risk_score', $chromeAlert->alert_type);
        $this->assertSame($chrome->id, $chromeAlert->process_id);
        $this->assertSame('chrome.exe triggered risk scoring rules (first_seen)', $chromeAlert->message);
        $this->assertEquals(
            ['score' => 25, 'reasons' => ['first_seen'], 'source' => 'C:/Program Files/chrome.exe', 'pid' => 101],
            $chromeAlert->details
        );

        $evilAlert = $alerts->firstWhere('severity', 'high');
        $this->assertSame($evil->id, $evilAlert->process_id);
        $this->assertSame('evil.exe triggered risk scoring rules (first_seen, virustotal malicious)', $evilAlert->message);

        // The first-seen registry and VT cache are maintained server-side.
        $seen = DB::table('processes_seen')->whereIn('file_hash', [$hashNew, $hashVt])->get();
        $this->assertCount(2, $seen);
        $vtRow = $seen->firstWhere('file_hash', $hashVt);
        $this->assertSame(6, (int) $vtRow->vt_malicious_count);
        $this->assertSame(70, (int) $vtRow->vt_total_engines);
        $this->assertNotNull($vtRow->vt_checked_at);
        $this->assertNull($seen->firstWhere('file_hash', $hashNew)->vt_checked_at);
    }

    public function test_a_retried_post_with_the_same_scan_uuid_is_deduplicated(): void
    {
        $token = $this->agentToken();
        $payload = [
            'snapshot' => json_encode([
                'stats' => [], 'processes' => [], 'alerts' => [],
                'generated_at' => now()->toIso8601String(),
            ]),
            'process_count' => 0,
            'scan_uuid' => (string) Str::uuid(),
            'processes' => [],
        ];

        $first = $this->postJson('/api/monitoring/snapshot', $payload, $this->bearer($token))
            ->assertCreated();

        $this->postJson('/api/monitoring/snapshot', $payload, $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('duplicate', true)
            ->assertJsonPath('snapshot_id', $first->json('snapshot_id'));

        $this->assertSame(1, MonitoringSnapshot::count());
        $this->assertSame(0, Process::count());
    }

    public function test_successful_and_retried_posts_refresh_agent_presence(): void
    {
        $token = $this->agentToken();
        $payload = [
            'snapshot' => json_encode([
                'stats' => [], 'processes' => [], 'alerts' => [],
                'generated_at' => now()->toIso8601String(),
            ]),
            'process_count' => 0,
            'scan_uuid' => (string) Str::uuid(),
            'hostname' => 'hotspot-laptop',
            'processes' => [],
        ];

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.43.2'])
            ->postJson('/api/monitoring/snapshot', $payload, $this->bearer($token))
            ->assertCreated();

        $agent = Agent::where('hostname', 'hotspot-laptop')->firstOrFail();
        $this->assertSame('192.168.43.2', $agent->ip_address);
        $this->assertSame('online', $agent->status);
        $this->assertTrue($agent->last_seen->greaterThan(now()->subMinute()));

        $previousSeen = now()->subMinute();
        $agent->update(['last_seen' => $previousSeen]);

        $this->withServerVariables(['REMOTE_ADDR' => '192.168.43.3'])
            ->postJson('/api/monitoring/snapshot', $payload, $this->bearer($token))
            ->assertOk()
            ->assertJsonPath('duplicate', true);

        $agent->refresh();
        $this->assertSame(1, Agent::count());
        $this->assertSame('192.168.43.3', $agent->ip_address);
        $this->assertTrue($agent->last_seen->greaterThan($previousSeen));
    }

    public function test_agent_ip_uses_forwarded_for_from_a_trusted_proxy(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.42'])
            ->postJson('/api/monitoring/snapshot', [
                'snapshot' => json_encode(['processes' => []]),
                'hostname' => 'tunnel-laptop',
                'processes' => [],
            ], $this->bearer($this->agentToken()))
            ->assertCreated();

        $this->assertSame('198.51.100.42', Agent::where('hostname', 'tunnel-laptop')->firstOrFail()->ip_address);
    }

    public function test_forwarded_ip_is_ignored_when_the_request_peer_is_not_trusted(): void
    {
        config(['trustedproxy.proxies' => '10.0.0.1']);

        $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-For' => '198.51.100.42'])
            ->postJson('/api/monitoring/snapshot', [
                'snapshot' => json_encode(['processes' => []]),
                'hostname' => 'direct-laptop',
                'processes' => [],
            ], $this->bearer($this->agentToken()))
            ->assertCreated();

        $this->assertSame('127.0.0.1', Agent::where('hostname', 'direct-laptop')->firstOrFail()->ip_address);
    }

    public function test_legacy_post_stores_embedded_alerts_verbatim(): void
    {
        $snapshot = [
            'processes' => [
                ['pid' => 1, 'name' => 'legacy.exe', 'path' => 'C:/legacy.exe', 'risk_level' => 'high'],
            ],
            'alerts' => [
                ['process_id' => null, 'alert_type' => 'manual', 'severity' => 'high',
                 'message' => 'manually attached', 'details' => null],
            ],
            'generated_at' => now()->toIso8601String(),
        ];

        $this->postJson('/api/monitoring/snapshot', ['snapshot' => json_encode($snapshot)], $this->bearer($this->agentToken()))
            ->assertCreated();

        $this->assertSame(1, Alert::count());
        $alert = Alert::first();
        $this->assertSame('manual', $alert->alert_type);
        $this->assertSame('manually attached', $alert->message);
        $this->assertSame(1, Process::count());
        $this->assertNull(MonitoringSnapshot::first()->hostname);
        $this->assertNull(Process::first()->hostname);
        $this->assertNull(Alert::first()->hostname);
    }

    public function test_malformed_process_rows_are_rejected(): void
    {
        $this->postJson('/api/monitoring/snapshot', [
            'snapshot' => json_encode(['generated_at' => now()->toIso8601String()]),
            'scan_uuid' => (string) Str::uuid(),
            'processes' => [['name' => 'bad.exe', 'score' => 'not-a-number']],
        ], $this->bearer($this->agentToken()))
            ->assertStatus(422);

        $this->assertSame(0, MonitoringSnapshot::count());
    }
}

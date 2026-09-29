<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndReportsTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_role(): void
    {
        User::factory()->create([
            'email' => 'analyst@sentinel.local',
            'password' => 'password',
            'role' => UserRole::Analyst,
        ]);

        $response = $this->post('/login', [
            'email' => 'analyst@sentinel.local',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs(User::where('email', 'analyst@sentinel.local')->first());
    }

    public function test_login_redirect_uses_https_from_a_trusted_proxy(): void
    {
        config([
            'session.secure' => true,
            'session.http_only' => true,
            'session.same_site' => 'lax',
        ]);

        User::factory()->create([
            'email' => 'https-user@sentinel.local',
            'password' => 'password',
            'role' => UserRole::Analyst,
        ]);

        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->withHeaders(['X-Forwarded-Proto' => 'https'])
            ->post('/login', [
                'email' => 'https-user@sentinel.local',
                'password' => 'password',
            ]);

        $response->assertRedirect();
        $this->assertStringStartsWith('https://', $response->headers->get('Location'));

        $cookie = collect($response->headers->getCookies())
            ->first(fn ($cookie) => $cookie->getName() === config('session.cookie'));
        $this->assertNotNull($cookie);
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertSame('lax', $cookie->getSameSite());
    }

    public function test_failed_login_attempts_are_throttled(): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', [
                'email' => 'missing-user@sentinel.local',
                'password' => 'wrong-password',
            ])->assertSessionHasErrors('email');
        }

        $this->post('/login', [
            'email' => 'missing-user@sentinel.local',
            'password' => 'wrong-password',
        ])->assertTooManyRequests();
    }

    public function test_reports_page_lists_available_reports(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->get('/reports');

        $response->assertOk();
        $response->assertSee('Daily Process Audit');
        $response->assertSee('Risk Summary');
        $response->assertSee('Threat Intelligence');
    }

    public function test_viewer_cannot_view_or_export_reports(): void
    {
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($viewer)->get('/reports/daily-audit')->assertForbidden();
        $this->actingAs($viewer)->get('/reports/daily-audit/export')->assertForbidden();
    }

    public function test_analyst_can_export_reports(): void
    {
        $analyst = User::factory()->create(['role' => UserRole::Analyst]);

        $this->actingAs($analyst)
            ->get('/reports/risk-summary/export')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }
}

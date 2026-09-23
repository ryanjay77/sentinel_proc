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

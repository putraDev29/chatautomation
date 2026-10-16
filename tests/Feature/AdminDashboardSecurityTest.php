<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\BintangMobilDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminDashboardSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(BintangMobilDemoSeeder::class);
        $this->admin = User::first();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/admin/dashboard');
        $response->assertRedirect('/login');

        $responseInbox = $this->get('/admin/conversations');
        $responseInbox->assertRedirect('/login');
    }

    public function test_admin_can_login_with_demo_credentials(): void
    {
        $response = $this->post('/login', [
            'email' => 'admin@bintangmobil.test',
            'password' => 'admin123456',
        ]);

        $response->assertRedirect('/admin/dashboard');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_authenticated_admin_can_view_all_sections(): void
    {
        $this->actingAs($this->admin);

        // Dashboard
        $dashboardRes = $this->get('/admin/dashboard');
        $dashboardRes->assertStatus(200);
        $dashboardRes->assertSee('Showroom Bintang Mobil');

        // Inbox
        $inboxRes = $this->get('/admin/conversations');
        $inboxRes->assertStatus(200);
        $inboxRes->assertSee('Daftar Percakapan');

        // Cars Inventory
        $carsRes = $this->get('/admin/cars');
        $carsRes->assertStatus(200);
        $carsRes->assertSee('Katalog Unit Showroom');

        // Leads Pipeline
        $leadsRes = $this->get('/admin/leads');
        $leadsRes->assertStatus(200);
        $leadsRes->assertSee('Manajemen Lead');

        // Settings
        $settingsRes = $this->get('/admin/settings');
        $settingsRes->assertStatus(200);
        $settingsRes->assertSee('Pengaturan Sistem');
    }

    public function test_settings_validation_blocks_invalid_business_hours(): void
    {
        $this->actingAs($this->admin);

        $response = $this->put('/admin/settings', [
            'whatsapp_mode' => 'simulation',
            'ai_driver' => 'simulation',
            'ai_model' => 'gpt-4o-mini',
            'business_hours_start' => 'invalid-time',
            'business_hours_end' => '21:00',
            'default_greeting' => 'Halo',
            'daily_ai_limit' => 500,
        ]);

        $response->assertSessionHasErrors(['business_hours_start']);
    }
}

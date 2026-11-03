<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_page_is_available_when_there_are_no_users(): void
    {
        $response = $this->get(route('setup'));

        $response->assertOk();
        $response->assertSee('Create your admin account');
    }

    public function test_the_first_admin_can_be_created_without_verification(): void
    {
        $response = $this->post(route('setup.store'), [
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertRedirect(route('dashboard'));

        $user = User::query()->firstOrFail();

        $this->assertSame('admin@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_setup_page_redirects_once_an_admin_exists(): void
    {
        User::factory()->create();

        $this->get(route('setup'))->assertRedirect(route('login'));
    }

    public function test_setup_page_redirects_authenticated_users_to_the_dashboard(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('setup'))->assertRedirect(route('dashboard'));
    }

    public function test_a_second_admin_cannot_be_created(): void
    {
        User::factory()->create();

        $this->post(route('setup.store'), [
            'name' => 'Second Admin',
            'email' => 'second@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertRedirect(route('login'));

        $this->assertSame(1, User::query()->count());
    }

    public function test_the_login_page_links_to_setup_only_when_there_are_no_users(): void
    {
        $this->get(route('login'))->assertSee('Create the admin account');

        User::factory()->create();

        $this->get(route('login'))->assertDontSee('Create the admin account');
    }

    public function test_the_home_page_links_to_setup_on_a_fresh_install(): void
    {
        $this->get(route('home'))->assertSee('Create admin account');

        User::factory()->create();

        $this->get(route('home'))->assertSee('Log in');
    }
}

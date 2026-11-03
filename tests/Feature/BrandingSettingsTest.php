<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class BrandingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $this->get(route('settings.branding'))->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_visit_the_branding_settings_page(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->get(route('settings.branding'));

        $response->assertOk();
        $response->assertSee('Logo');
        $response->assertSee('Browser icon');
    }

    public function test_a_logo_can_be_uploaded(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings.branding')
            ->set('logo', UploadedFile::fake()->image('logo.png'))
            ->call('save')
            ->assertHasNoErrors();

        $settings = Setting::current();

        $this->assertNotNull($settings->logo_path);

        Storage::disk('public')->assertExists($settings->logo_path);
    }

    public function test_an_icon_can_be_uploaded(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        Livewire::test('pages::settings.branding')
            ->set('icon', UploadedFile::fake()->image('icon.png'))
            ->call('save')
            ->assertHasNoErrors();

        $settings = Setting::current();

        $this->assertNotNull($settings->icon_path);

        Storage::disk('public')->assertExists($settings->icon_path);
    }

    public function test_the_uploaded_icon_is_used_in_the_browser_tab(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        Storage::disk('public')->put('icons/favicon.png', 'icon');

        Setting::current()->update(['icon_path' => 'icons/favicon.png']);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(Storage::disk('public')->url('icons/favicon.png'), false);
    }

    public function test_the_uploaded_icon_is_used_in_the_app_sidebar(): void
    {
        Storage::fake('public');
        $this->actingAs(User::factory()->create());

        Storage::disk('public')->put('icons/app-icon.png', 'icon');

        Setting::current()->update(['icon_path' => 'icons/app-icon.png']);

        $response = $this->get(route('dashboard'));

        $response->assertOk();
        $response->assertSee(Storage::disk('public')->url('icons/app-icon.png'), false);
        $response->assertSee('alt="'.config('app.name').'"', false);
    }

    public function test_the_uploaded_icon_is_used_on_the_login_page(): void
    {
        Storage::fake('public');

        Storage::disk('public')->put('icons/login-icon.png', 'icon');

        Setting::current()->update(['icon_path' => 'icons/login-icon.png']);

        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(Storage::disk('public')->url('icons/login-icon.png'), false);
        $response->assertSee('alt="'.config('app.name').'"', false);
    }
}

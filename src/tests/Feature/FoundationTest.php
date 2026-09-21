<?php

namespace Tests\Feature;

use App\Livewire\Preferences;
use App\Livewire\Settings;
use App\Livewire\Staff;
use App\Models\User;
use App\Modules\Foundation\Actions;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Tests\TestCase;

class FoundationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role = 'manager', array $extra = []): User
    {
        return User::factory()->create(array_merge(['role' => $role, 'active' => true, 'locale' => 'fr', 'theme' => 'system'], $extra));
    }

    public function test_guests_are_redirected_and_api_requires_auth(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->get('/register')->assertNotFound();
    }

    public function test_login_and_logout(): void
    {
        $u = $this->user(extra: ['password' => 'CorrectPassword!']);
        $this->post('/login', ['email' => $u->email, 'password' => 'CorrectPassword!'])->assertRedirect('/');
        $this->assertAuthenticatedAs($u);
        $this->post('/logout')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_disabled_user_cannot_login_or_use_existing_session(): void
    {
        $u = $this->user(extra: ['active' => false, 'password' => 'CorrectPassword!']);
        $this->post('/login', ['email' => $u->email, 'password' => 'CorrectPassword!'])->assertSessionHasErrors('email');
        $this->actingAs($u)->get('/')->assertForbidden();
    }

    public function test_login_throttles_after_five_failures(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $response = $this->post('/login', ['email' => 'rate@example.test', 'password' => 'wrong']);
        }$response->assertSessionHasErrors('email');
        $this->assertStringContainsString('minute', session('errors')->first('email'));
    }

    public function test_non_managers_cannot_access_management_web_or_api(): void
    {
        foreach (['agent', 'finance'] as $role) {
            $u = $this->user($role);
            $this->actingAs($u)->get('/staff')->assertForbidden();
            $this->getJson('/api/v1/settings')->assertForbidden();
            $this->getJson('/api/v1/audit-events')->assertForbidden();
            $this->postJson('/api/v1/staff', [])->assertForbidden();
        }
    }

    public function test_token_scope_does_not_bypass_user_role(): void
    {
        $u = $this->user('agent');
        Sanctum::actingAs($u, ['*']);
        $this->getJson('/api/v1/staff')->assertForbidden();
    }

    public function test_restricted_manager_token_cannot_manage_staff(): void
    {
        $u = $this->user();
        $token = $u->createToken('read', ['profile.read'])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/staff')->assertForbidden();
    }

    public function test_staff_creation_is_shared_audited_and_hides_password(): void
    {
        $u = $this->user();
        $data = ['name' => 'Agent Test', 'email' => 'agent-new@example.test', 'role' => 'agent', 'active' => true, 'password' => 'Temporary!2026', 'reason' => 'New employee'];
        $this->actingAs($u)->postJson('/api/v1/staff', $data)->assertCreated()->assertJsonMissingPath('data.password');
        $this->assertDatabaseHas('users', ['email' => $data['email'], 'must_change_password' => true]);
        $this->assertDatabaseHas('audit_events', ['action' => 'staff.created', 'reason' => 'New employee']);
        $this->assertStringNotContainsString($data['password'], json_encode(DB::table('audit_events')->get()));
    }

    public function test_last_manager_cannot_be_demoted(): void
    {
        $u = $this->user();
        $this->actingAs($u)->patchJson('/api/v1/staff/'.$u->id, ['name' => $u->name, 'email' => $u->email, 'role' => 'agent', 'active' => true, 'reason' => 'Demotion'])->assertUnprocessable();
        $this->assertSame('manager', $u->fresh()->role);
    }

    public function test_reset_revokes_tokens_and_requires_password_change(): void
    {
        $manager = $this->user();
        $agent = $this->user('agent');
        $agent->createToken('mobile', ['*']);
        $this->actingAs($manager)->patchJson('/api/v1/staff/'.$agent->id, ['name' => $agent->name, 'email' => $agent->email, 'role' => 'agent', 'active' => true, 'password' => 'NewTemporary!2026', 'reason' => 'Recovery'])->assertOk();
        $this->assertSame(0, $agent->tokens()->count());
        $this->assertTrue($agent->fresh()->must_change_password);
    }

    public function test_forced_password_change_blocks_management_and_allows_recovery(): void
    {
        $u = $this->user(extra: ['must_change_password' => true, 'password' => 'OldPassword!2026']);
        $this->actingAs($u)->get('/')->assertRedirect('/preferences');
        $this->getJson('/api/v1/staff')->assertForbidden()->assertJsonPath('code', 'password_change_required');
        Livewire::test(Preferences::class)->set('current_password', 'OldPassword!2026')->set('password', 'NewPassword!2026')->set('password_confirmation', 'NewPassword!2026')->call('changePassword')->assertHasNoErrors();
        $this->assertFalse($u->fresh()->must_change_password);
        $this->assertTrue(Hash::check('NewPassword!2026', $u->fresh()->password));
    }

    public function test_livewire_staff_cannot_bypass_permissions(): void
    {
        $this->actingAs($this->user('agent'));
        Livewire::test(Staff::class)->assertForbidden();
    }

    public function test_livewire_settings_and_api_share_validation_and_audit(): void
    {
        $this->actingAs($this->user());
        Livewire::test(Settings::class)->set('preparation_minutes', 180)->set('reason', 'Longer inspection')->call('save')->assertHasNoErrors();
        $this->getJson('/api/v1/settings')->assertJsonPath('data.preparation_minutes', 180);
        $this->putJson('/api/v1/settings', ['preparation_minutes' => -1])->assertUnprocessable();
        $this->assertDatabaseHas('audit_events', ['action' => 'settings.updated']);
    }

    public function test_preferences_persist_all_languages_and_themes(): void
    {
        $u = $this->user();
        $this->actingAs($u);
        foreach (['fr', 'ar', 'en'] as $locale) {
            foreach (['light', 'dark', 'system'] as $theme) {
                $this->patchJson('/api/v1/me/preferences', compact('locale', 'theme'))->assertOk();
                $this->get('/preferences')->assertOk()->assertSee('lang="'.$locale.'"', false)->assertSee('dir="'.($locale === 'ar' ? 'rtl' : 'ltr').'"', false);
            }
        }
    }

    public function test_management_pages_render(): void
    {
        $this->actingAs($this->user());
        foreach (['/', '/staff', '/settings', '/audit', '/preferences'] as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_audit_rows_are_immutable_at_database_level(): void
    {
        $u = $this->user();
        app(Actions::class)->audit($u, 'settings.updated', 'agency', 1, null, ['x' => 1]);
        $this->expectException(QueryException::class);
        DB::table('audit_events')->update(['reason' => 'tampered']);
    }

    public function test_translation_catalogs_match(): void
    {
        $base = array_keys(require lang_path('fr/ui.php'));
        foreach (['ar', 'en'] as $locale) {
            $this->assertSame($base, array_keys(require lang_path($locale.'/ui.php')));
        }
    }
}

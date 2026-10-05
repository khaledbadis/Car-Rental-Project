<?php

namespace Tests\Feature;

use App\Livewire\ProfileMenu;
use App\Livewire\Staff;
use App\Models\User;
use App\Modules\Foundation\Actions;
use App\Modules\Foundation\AuditPresenter;
use App\Modules\Foundation\Notifications;
use App\Modules\Release\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UiRefinementTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_switchers_validate_and_persist(): void
    {
        $u = User::factory()->create(['role' => 'manager', 'active' => true, 'locale' => 'en', 'theme' => 'system']);
        Livewire::actingAs($u)->test(ProfileMenu::class)->call('choose', 'theme', 'dark')->assertRedirect();
        $this->assertSame('dark', $u->fresh()->theme);
        Livewire::actingAs($u)->test(ProfileMenu::class)->call('choose', 'locale', 'invalid')->assertHasErrors('locale');
    }

    public function test_team_delete_revokes_access_and_retains_user(): void
    {
        $u = User::factory()->create(['role' => 'manager', 'active' => true, 'locale' => 'en', 'theme' => 'system']);
        $target = User::factory()->create(['role' => 'agent', 'active' => true]);
        $target->createToken('mobile');
        Livewire::actingAs($u)->test(Staff::class)->call('create')->assertSet('showEditor', true)->call('confirmDelete', $target->id)->set('deleteReason', 'Left agency')->call('delete')->assertHasNoErrors()->assertSet('showDelete', false);
        $this->assertFalse($target->fresh()->active);
        $this->assertSame(0, $target->tokens()->count());
        Livewire::actingAs($u)->test(Staff::class)->call('confirmDelete', $u->id)->set('deleteReason', 'Self')->call('delete')->assertHasErrors('deleteReason');
    }

    public function test_notifications_are_user_scoped_locale_stable_and_daily(): void
    {
        $u = User::factory()->create(['role' => 'manager', 'active' => true, 'locale' => 'en', 'theme' => 'system']);
        $other = User::factory()->create(['role' => 'manager', 'active' => true, 'locale' => 'en', 'theme' => 'system']);
        $this->mock(Dashboard::class)->shouldReceive('read')->andReturn(['urgent' => [['kind' => 'maintenance', 'label' => 'Translated reminder', 'notification_key' => 'oil', 'url' => 'http://localhost/maintenance?vehicle_id=1']]]);
        $a = app(Notifications::class);
        $id = $a->read($u)[0]['id'];
        $a->mark($u, $id);
        app()->setLocale('ar');
        $this->assertTrue($a->read($u)[0]['read']);
        $this->assertFalse($a->read($other)[0]['read']);
        $this->travel(1)->days();
        $this->assertFalse($a->read($u)[0]['read']);
    }

    public function test_audit_changes_are_readable_and_secrets_omitted(): void
    {
        $rows = app(AuditPresenter::class)->changes('{"active":true,"amount_cents":100,"password":"old"}', '{"active":false,"amount_cents":200,"password":"new"}');
        $this->assertIsString(app(AuditPresenter::class)->label('status'));
        $this->assertIsString(app(AuditPresenter::class)->label('0'));
        $this->assertCount(2, $rows);
        $this->assertSame('2.00 DZD', $rows[1]['after']);
        $this->assertSame(__('shell.no'), $rows[0]['after']);
    }

    public function test_refined_pages_render_in_all_languages(): void
    {
        foreach (['en', 'fr', 'ar'] as $locale) {
            $u = User::factory()->create(['role' => 'manager', 'active' => true, 'locale' => $locale]);
            app(Actions::class)->audit($u, 'profile.updated', 'user', $u->id, null, ['fields' => ['phone', 'address'], 'status' => 'active']);
            foreach (['/staff', '/preferences', '/audit'] as $url) {
                $this->actingAs($u)->get($url)->assertOk()->assertSee('Qualicar Algerie')->assertDontSee('shell.');
            }
        }
    }
}

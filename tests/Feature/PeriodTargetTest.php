<?php

namespace Tests\Feature;

use App\Models\PeriodTarget;
use App\Models\Project;
use App\Models\SocialPlatform;
use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Uzzal\Acl\Middleware\AuthenticateWithAcl;
use Uzzal\Acl\Middleware\ResourceMaker;

class PeriodTargetTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $agent;
    private SocialPlatform $facebook;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // route permissions are covered by the ACL package, these tests are about the feature itself
        $this->withoutMiddleware(array_values(array_filter([
            config('jetstream.auth_session'),
            EnsureEmailIsVerified::class,
            ResourceMaker::class,
            AuthenticateWithAcl::class,
        ])));

        Carbon::setTestNow('2026-10-06 10:00:00');

        $this->manager = User::factory()->create(['usages_sector' => 'corporate']);
        $this->agent = User::factory()->create(['usages_sector' => 'field', 'name' => 'Agent One']);
        $this->facebook = SocialPlatform::createOrUpdateSocialPlatform(['name' => 'Facebook', 'has_comments' => true]);
        $this->project = Project::createOrUpdateProject(['name' => 'Inbound Support', 'has_outbound_calls' => true]);
        $this->actingAs($this->manager);
    }

    public function test_monthly_target_is_set_listed_edited_and_deleted(): void
    {
        $payload = [
            'user_ids' => [$this->agent->id],
            'month' => '2026-10',
            'outbound_calls' => 2000,
            'inbound_calls' => null,
            'platforms' => [['social_platform_id' => $this->facebook->id, 'comments' => 300, 'message_replies' => null]],
            'projects' => [['project_id' => $this->project->id, 'inbound_calls' => 150]],
        ];

        $this->postJson('/period-targets', $payload)->assertCreated()->assertJsonPath('data.users', 1);

        $stored = PeriodTarget::query()->where('user_id', $this->agent->id)->pluck('target', 'activity')->all();

        // what was left empty is no target
        $this->assertSame([
            'outbound_calls' => 2000,
            'platform:'.$this->facebook->id.':comments' => 300,
            'project:'.$this->project->id.':inbound_calls' => 150,
        ], $stored);

        $this->get('/period-targets?month=2026-10')
            ->assertOk()
            ->assertSee('Agent One')
            ->assertSee('Outbound Calls: 2000', false)
            ->assertSee('Facebook (Comments): 300', false);

        // setting it again replaces the month as a whole
        $this->postJson('/period-targets', ['user_ids' => [$this->agent->id], 'month' => '2026-10', 'outbound_calls' => 1500])->assertCreated();
        $this->assertSame(['outbound_calls' => 1500], PeriodTarget::query()->pluck('target', 'activity')->all());

        // another month is untouched
        $this->postJson('/period-targets', ['user_ids' => [$this->agent->id], 'month' => '2026-11', 'outbound_calls' => 900])->assertCreated();
        $this->assertDatabaseCount('period_targets', 2);

        $this->get('/period-targets?month=2026-10&user='.$this->agent->id)->assertOk()->assertSee('value="1500"', false);

        $this->deleteJson("/period-targets/{$this->agent->id}?month=2026-10")->assertOk();
        $this->assertDatabaseCount('period_targets', 1);
        $this->assertSame(900, PeriodTarget::query()->value('target'));
    }

    public function test_monthly_target_is_validated(): void
    {
        $office = User::factory()->create(['usages_sector' => 'corporate']);

        $this->postJson('/period-targets', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids', 'month', 'outbound_calls']);

        $this->postJson('/period-targets', ['user_ids' => [$office->id], 'month' => '2026-10-01', 'outbound_calls' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids.0', 'month', 'outbound_calls']);

        $this->assertDatabaseCount('period_targets', 0);
    }
}

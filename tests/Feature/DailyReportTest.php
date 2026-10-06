<?php

namespace Tests\Feature;

use App\Http\Controllers\DailyReportController;
use App\Models\DailyReport;
use App\Models\Project;
use App\Models\SocialPlatform;
use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;
use Uzzal\Acl\Middleware\AuthenticateWithAcl;
use Uzzal\Acl\Middleware\ResourceMaker;
use Uzzal\Acl\Services\PermissionCheckService;

class DailyReportTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resetAclCache();

        // route permissions are covered by the ACL package, these tests are about the feature itself
        $this->withoutMiddleware(array_values(array_filter([
            config('jetstream.auth_session'),
            EnsureEmailIsVerified::class,
            ResourceMaker::class,
            AuthenticateWithAcl::class,
        ])));

        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_social_platform_can_be_created_updated_and_deleted(): void
    {
        $this->postJson('/social-platforms', ['name' => 'Facebook', 'active' => 1])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'facebook');

        $this->postJson('/social-platforms', ['name' => 'Facebook', 'active' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);

        $platform = SocialPlatform::query()->firstOrFail();

        $this->putJson("/social-platforms/{$platform->id}", ['name' => 'Meta'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'meta')
            ->assertJsonPath('data.active', false);

        $this->deleteJson("/social-platforms/{$platform->id}")->assertOk();
        $this->assertDatabaseCount('social_platforms', 0);
    }

    public function test_platform_and_project_with_report_data_cannot_be_deleted(): void
    {
        [$platform, $project] = $this->platformAndProject();
        $this->postJson('/daily-reports', $this->payload($platform, $project))->assertCreated();

        $this->deleteJson("/social-platforms/{$platform->id}")->assertStatus(409);
        $this->deleteJson("/projects/{$project->id}")->assertStatus(409);

        $this->assertDatabaseHas('social_platforms', ['id' => $platform->id]);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_report_is_saved_with_its_platform_and_project_rows(): void
    {
        [$platform, $project] = $this->platformAndProject();

        $this->postJson('/daily-reports', $this->payload($platform, $project))
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.report_date', today()->toDateString());

        $report = DailyReport::query()->firstOrFail();

        $this->assertSame($this->user->id, $report->user_id);
        $this->assertSame(40, $report->outbound_calls);
        $this->assertSame('Busy day', $report->outbound_calls_note);
        $this->assertDatabaseHas('daily_report_platform_replies', [
            'daily_report_id' => $report->id,
            'social_platform_id' => $platform->id,
            'total_replies' => 12,
            'note' => 'Campaign post',
        ]);
        $this->assertDatabaseHas('daily_report_project_calls', [
            'daily_report_id' => $report->id,
            'project_id' => $project->id,
            'total_calls' => 25,
            'note' => null,
        ]);
    }

    public function test_saving_the_same_date_again_updates_the_report(): void
    {
        [$platform, $project] = $this->platformAndProject();

        $this->postJson('/daily-reports', $this->payload($platform, $project))->assertCreated();

        $second = $this->payload($platform, $project);
        $second['outbound_calls'] = 55;
        $second['projects'][0]['total_calls'] = 30;
        $second['platforms'] = [];

        $this->postJson('/daily-reports', $second)->assertOk();

        $this->assertDatabaseCount('daily_reports', 1);
        $this->assertDatabaseCount('daily_report_project_calls', 1);
        $this->assertDatabaseHas('daily_reports', ['outbound_calls' => 55]);
        $this->assertDatabaseHas('daily_report_project_calls', ['total_calls' => 30]);
        // rows left out of the submission are removed
        $this->assertDatabaseCount('daily_report_platform_replies', 0);
    }

    public function test_invalid_reports_are_rejected(): void
    {
        [$platform, $project] = $this->platformAndProject();
        $inactiveProject = Project::createOrUpdateProject(['name' => 'Closed', 'active' => false]);

        $payload = $this->payload($platform, $project);
        $payload['report_date'] = today()->addDay()->toDateString();
        $payload['outbound_calls'] = -1;
        $payload['platforms'][0]['total_replies'] = 'many';
        $payload['projects'][] = ['project_id' => $inactiveProject->id, 'total_calls' => 3];
        $payload['projects'][] = ['project_id' => $project->id, 'total_calls' => 1];

        $this->postJson('/daily-reports', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors([
                'report_date',
                'outbound_calls',
                'platforms.0.total_replies',
                'projects.1.project_id',
                'projects.2.project_id',
            ]);

        $this->assertDatabaseCount('daily_reports', 0);
    }

    public function test_deactivated_project_stays_on_a_report_that_already_has_it(): void
    {
        [$platform, $project] = $this->platformAndProject();
        $this->postJson('/daily-reports', $this->payload($platform, $project))->assertCreated();
        $report = DailyReport::query()->firstOrFail();

        $project->update(['active' => false]);

        $this->get("/daily-reports/{$report->id}/edit")
            ->assertOk()
            ->assertSee('Inbound Support')
            ->assertSee('Inactive');

        $payload = $this->payload($platform, $project);
        $payload['projects'][0]['total_calls'] = 26;

        $this->putJson("/daily-reports/{$report->id}", $payload)->assertOk();
        $this->assertDatabaseHas('daily_report_project_calls', ['project_id' => $project->id, 'total_calls' => 26]);

        // a report for another day no longer offers the project
        $yesterday = today()->subDay()->toDateString();
        $this->get("/daily-reports/create?date={$yesterday}")
            ->assertOk()
            ->assertDontSee('Inbound Support');

        $payload['report_date'] = $yesterday;
        $this->postJson('/daily-reports', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['projects.0.project_id']);
    }

    public function test_update_cannot_move_a_report_onto_a_date_that_already_has_one(): void
    {
        [$platform, $project] = $this->platformAndProject();
        $yesterday = today()->subDay()->toDateString();

        $this->postJson('/daily-reports', $this->payload($platform, $project))->assertCreated();
        $this->postJson('/daily-reports', ['report_date' => $yesterday] + $this->payload($platform, $project))->assertCreated();

        $todayReport = DailyReport::query()->whereDate('report_date', today())->firstOrFail();

        $this->putJson("/daily-reports/{$todayReport->id}", ['report_date' => $yesterday] + $this->payload($platform, $project))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['report_date']);

        $this->assertDatabaseCount('daily_reports', 2);
    }

    public function test_report_of_another_user_cannot_be_viewed_or_changed(): void
    {
        [$platform, $project] = $this->platformAndProject();
        $this->postJson('/daily-reports', $this->payload($platform, $project))->assertCreated();
        $report = DailyReport::query()->firstOrFail();

        $this->actingAs(User::factory()->create());

        $this->getJson("/daily-reports/{$report->id}")->assertForbidden();
        $this->get("/daily-reports/{$report->id}/edit")->assertForbidden();
        $this->putJson("/daily-reports/{$report->id}", $this->payload($platform, $project))->assertForbidden();
        $this->deleteJson("/daily-reports/{$report->id}")->assertForbidden();

        $this->assertDatabaseHas('daily_reports', ['id' => $report->id, 'outbound_calls' => 40]);
    }

    public function test_user_with_team_access_can_view_but_not_change_reports_of_others(): void
    {
        [$platform, $project] = $this->platformAndProject();
        $this->postJson('/daily-reports', $this->payload($platform, $project))->assertCreated();
        $report = DailyReport::query()->firstOrFail();

        $manager = User::factory()->create();
        $this->grantTeamAccess($manager);
        $this->actingAs($manager);

        $this->getJson("/daily-reports/{$report->id}")
            ->assertOk()
            ->assertJsonPath('id', $report->id)
            ->assertJsonPath('project_calls.0.project.name', 'Inbound Support');
        $this->get("/daily-reports/{$report->id}")->assertOk()->assertSee('Inbound Support');

        $this->putJson("/daily-reports/{$report->id}", $this->payload($platform, $project))->assertForbidden();
        $this->deleteJson("/daily-reports/{$report->id}")->assertForbidden();
    }

    public function test_history_lists_own_reports_and_team_list_covers_everyone(): void
    {
        [$platform, $project] = $this->platformAndProject();
        $this->postJson('/daily-reports', $this->payload($platform, $project))->assertCreated();

        $other = User::factory()->create();
        $this->actingAs($other);
        $this->postJson('/daily-reports', $this->payload($platform, $project))->assertCreated();

        $ajax = ['X-Requested-With' => 'XMLHttpRequest'];

        $this->getJson('/daily-reports?draw=1', $ajax)
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.user_id', $other->id)
            ->assertJsonPath('data.0.is_own', true)
            ->assertJsonPath('data.0.platform_replies_total', 12)
            ->assertJsonPath('data.0.project_calls_total', 25);

        $this->getJson('/daily-reports/team?draw=1', $ajax)
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2);

        $this->getJson("/daily-reports/team?draw=1&user_id={$this->user->id}", $ajax)
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.is_own', false);

        $tomorrow = today()->addDay()->toDateString();
        $this->getJson("/daily-reports/team?draw=1&from={$tomorrow}", $ajax)
            ->assertOk()
            ->assertJsonPath('recordsTotal', 0);
    }

    public function test_owner_can_delete_a_report_with_its_rows(): void
    {
        [$platform, $project] = $this->platformAndProject();
        $this->postJson('/daily-reports', $this->payload($platform, $project))->assertCreated();
        $report = DailyReport::query()->firstOrFail();

        $this->deleteJson("/daily-reports/{$report->id}")->assertOk()->assertJsonPath('success', true);

        $this->assertDatabaseCount('daily_reports', 0);
        $this->assertDatabaseCount('daily_report_platform_replies', 0);
        $this->assertDatabaseCount('daily_report_project_calls', 0);
    }

    public function test_entry_form_lists_active_platforms_and_projects(): void
    {
        $this->platformAndProject();
        SocialPlatform::createOrUpdateSocialPlatform(['name' => 'Orkut', 'active' => false]);

        $this->get('/daily-reports/create')
            ->assertOk()
            ->assertSee('Facebook')
            ->assertSee('Inbound Support')
            ->assertDontSee('Orkut')
            ->assertSee('name="platforms[0][total_replies]"', false);
    }

    /**
     * @return array{0: SocialPlatform, 1: Project}
     */
    private function platformAndProject(): array
    {
        return [
            SocialPlatform::createOrUpdateSocialPlatform(['name' => 'Facebook']),
            Project::createOrUpdateProject(['name' => 'Inbound Support']),
        ];
    }

    private function payload(SocialPlatform $platform, Project $project): array
    {
        return [
            'report_date' => today()->toDateString(),
            'outbound_calls' => 40,
            'outbound_calls_note' => 'Busy day',
            'inbound_calls' => 15,
            'message_replies' => 22,
            'platforms' => [
                ['social_platform_id' => $platform->id, 'total_replies' => 12, 'note' => 'Campaign post'],
            ],
            'projects' => [
                ['project_id' => $project->id, 'total_calls' => 25],
            ],
        ];
    }

    /**
     * Give the user a role that is permitted to open the team report list.
     */
    private function grantTeamAccess(User $user): void
    {
        $action = DailyReportController::class.'@team';
        $roleId = DB::table('roles')->insertGetId(['name' => 'manager']);

        DB::table('resources')->insert([
            'resource_id' => sha1($action),
            'name' => 'DailyReport GET::Team',
            'controller' => 'DailyReport',
            'action' => $action,
        ]);
        DB::table('permissions')->insert(['role_id' => $roleId, 'resource_id' => sha1($action)]);
        DB::table('user_roles')->insert(['user_id' => $user->id, 'role_id' => $roleId]);
    }

    /**
     * The ACL package keeps the permissions of the first user it sees for the whole process.
     */
    private function resetAclCache(): void
    {
        $defaults = [
            '_roles' => null,
            '_role_names' => null,
            '_resources' => [],
            '_permission_rows' => [],
            '_resource_group' => [],
        ];

        foreach ($defaults as $property => $value) {
            (new \ReflectionProperty(PermissionCheckService::class, $property))->setValue(null, $value);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\DailyTarget;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Uzzal\Acl\Middleware\AuthenticateWithAcl;
use Uzzal\Acl\Middleware\ResourceMaker;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $agent;
    private User $otherAgent;
    private SocialPlatform $platform;
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

        // a Tuesday, so the week in progress started on Saturday the 3rd
        Carbon::setTestNow('2026-10-06 10:00:00');

        $this->manager = User::factory()->create(['usages_sector' => 'corporate']);
        $this->agent = User::factory()->create(['usages_sector' => 'field', 'name' => 'Agent One', 'created_at' => '2026-09-01 09:00:00']);
        $this->otherAgent = User::factory()->create(['usages_sector' => 'field', 'name' => 'Agent Two', 'created_at' => '2026-09-01 09:00:00']);
        $this->platform = SocialPlatform::createOrUpdateSocialPlatform(['name' => 'Facebook']);
        $this->project = Project::createOrUpdateProject(['name' => 'Inbound Support']);

        $this->report($this->agent, '2026-10-06', 40, 12, 25);
        $this->report($this->agent, '2026-10-05', 30, 6, 15);
        $this->report($this->otherAgent, '2026-10-06', 10, 3, 5);
        // the day before the 2-day range ending today, for the comparison
        $this->report($this->agent, '2026-10-04', 20, 4, 10);

        DailyTarget::setForUsers([$this->agent->id, $this->otherAgent->id], ['2026-10-05', '2026-10-06'], [
            'outbound_calls' => 50,
            'platforms' => [['social_platform_id' => $this->platform->id, 'comments' => 10]],
        ], $this->manager->id);
    }

    public function test_dashboard_page_offers_the_user_filter_to_corporate_users_only(): void
    {
        $this->actingAs($this->manager)->get('/dashboard')
            ->assertOk()
            ->assertSee('id="dashboard_user"', false)
            ->assertSee('Agent Two');

        $this->actingAs($this->agent)->get('/dashboard')
            ->assertOk()
            ->assertDontSee('id="dashboard_user"', false)
            ->assertDontSee('Agent Two');
    }

    public function test_corporate_user_sees_everyone_with_targets(): void
    {
        $response = $this->data($this->manager, ['preset' => 'custom', 'from' => '2026-10-05', 'to' => '2026-10-06'])
            ->assertOk()
            ->assertJsonPath('show_targets', true)
            ->assertJsonPath('range.previous_from', '2026-10-03')
            ->assertJsonPath('range.previous_to', '2026-10-04')
            ->assertJsonPath('trend.dates', ['2026-10-05', '2026-10-06'])
            ->assertJsonPath('trend.series.outbound_calls', [30, 50])
            ->assertJsonPath('platforms.0.name', 'Facebook')
            ->assertJsonPath('platforms.0.actual', 21)
            ->assertJsonPath('platforms.0.target', 40)
            ->assertJsonPath('projects.0.actual', 45)
            ->assertJsonPath('projects.0.target', null)
            // 2 working days for 2 field users, 3 reports in
            ->assertJsonPath('submissions.submitted', 3)
            ->assertJsonPath('submissions.expected', 4);

        $outbound = collect($response->json('kpis'))->firstWhere('key', 'outbound_calls');
        $this->assertSame(80, $outbound['actual']);
        $this->assertSame(20, $outbound['previous']);
        $this->assertEquals(300, $outbound['change_pct']);
        $this->assertSame(200, $outbound['target']);
        $this->assertEquals(40, $outbound['achievement_pct']);

        $inbound = collect($response->json('kpis'))->firstWhere('key', 'inbound_calls');
        $this->assertNull($inbound['target']);
        $this->assertNull($inbound['achievement_pct']);

        $this->assertArrayNotHasKey('users', $response->json());
        $this->assertArrayNotHasKey('attendance', $response->json());
    }

    public function test_people_section_pages_sorts_and_searches_users_on_the_server(): void
    {
        $range = ['preset' => 'custom', 'from' => '2026-10-05', 'to' => '2026-10-06', 'section' => 'people'];

        $response = $this->data($this->manager, $range)
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.last_page', 1)
            ->assertJsonPath('insights.users', 2);

        $users = collect($response->json('rows'))->keyBy('name');
        $this->assertSame(2, $users['Agent One']['reports']);
        $this->assertSame(70, $users['Agent One']['outbound_calls']);
        // 70 calls against the target of 100, the approximate comment target of 20 is not scored
        $this->assertSame(100, $users['Agent One']['target_total']);
        $this->assertEquals(70, $users['Agent One']['achievement_pct']);
        $this->assertNotNull($users['Agent One']['score']);
        // the best score first by default
        $this->assertSame('Agent One', $response->json('rows.0.name'));

        $this->data($this->manager, $range + ['per_page' => 5, 'sort' => 'name', 'dir' => 'desc'])
            ->assertJsonPath('rows.0.name', 'Agent Two');

        $this->data($this->manager, $range + ['search' => 'two'])
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('rows.0.name', 'Agent Two')
            // the spread of the team does not follow the search
            ->assertJsonPath('insights.users', 2);

        // a page past the end falls back to the last one
        $this->data($this->manager, $range + ['page' => 9])->assertJsonPath('meta.page', 1);

        $this->data($this->manager, $range + ['export' => 1, 'sort' => 'bogus'])->assertStatus(422)->assertJsonValidationErrors(['sort']);
        $this->data($this->manager, $range + ['export' => 1])->assertJsonCount(2, 'rows');
    }
    public function test_corporate_user_can_narrow_the_dashboard_to_one_user(): void
    {
        $response = $this->data($this->manager, ['preset' => 'today', 'user_id' => $this->otherAgent->id])
            ->assertOk()
            ->assertJsonPath('range.from', '2026-10-06')
            ->assertJsonPath('submissions.expected', 1);

        $this->assertSame(10, collect($response->json('kpis'))->firstWhere('key', 'outbound_calls')['actual']);

        $this->data($this->manager, ['preset' => 'today', 'user_id' => $this->otherAgent->id, 'section' => 'people'])
            ->assertJsonCount(1, 'rows');
    }

    public function test_field_user_sees_only_their_own_reports_and_no_targets(): void
    {
        $response = $this->data($this->otherAgent, ['preset' => 'today', 'user_id' => $this->agent->id])
            ->assertOk()
            ->assertJsonPath('show_targets', false)
            ->assertJsonPath('platforms.0.actual', 3)
            ->assertJsonPath('platforms.0.target', null)
            ->assertJsonPath('submissions.expected', 1);

        $outbound = collect($response->json('kpis'))->firstWhere('key', 'outbound_calls');
        $this->assertSame(10, $outbound['actual']);
        $this->assertNull($outbound['target']);

        // their own score only, with no target to show, and nothing of the team's day
        $people = $this->data($this->otherAgent, ['preset' => 'today', 'user_id' => $this->agent->id, 'section' => 'people'])->assertOk();
        $this->assertCount(1, $people->json('rows'));
        $this->assertSame('Agent Two', $people->json('rows.0.name'));
        $this->assertNull($people->json('rows.0.target_total'));
        $this->assertNull($people->json('rows.0.achievement_pct'));

        $this->data($this->otherAgent, ['section' => 'live'])->assertJsonPath('live', null);
    }

    public function test_periods_follow_the_saturday_week_and_skip_off_days_for_due_reports(): void
    {
        Holiday::createOrUpdateHoliday(['holiday_date' => '2026-10-04', 'title' => 'Govt. holiday']);

        // Saturday the 3rd to Tuesday the 6th, with Sunday off: 3 working days
        $this->data($this->manager, ['preset' => 'week'])
            ->assertOk()
            ->assertJsonPath('range.from', '2026-10-03')
            ->assertJsonPath('range.to', '2026-10-06')
            ->assertJsonPath('submissions.expected', 6);

        $this->data($this->manager, ['preset' => '15days'])->assertJsonPath('range.from', '2026-09-22');
        $this->data($this->manager, ['preset' => 'month'])->assertJsonPath('range.from', '2026-10-01');
        $this->data($this->manager, [])->assertJsonPath('range.from', '2026-10-01');
    }

    public function test_a_full_day_of_leave_is_not_a_report_due(): void
    {
        UserLeave::setForUsers([$this->otherAgent->id], ['2026-10-05'], ['portion' => 'full', 'type' => 'sick']);
        // a half day still takes a report, and a leave outside the range changes nothing
        UserLeave::setForUsers([$this->agent->id], ['2026-10-05'], ['portion' => 'half', 'type' => 'sick']);
        UserLeave::setForUsers([$this->agent->id], ['2026-10-03'], ['portion' => 'full', 'type' => 'sick']);

        // 2 working days for 2 field users, less the day of leave
        $this->data($this->manager, ['preset' => 'custom', 'from' => '2026-10-05', 'to' => '2026-10-06'])
            ->assertOk()
            ->assertJsonPath('submissions.submitted', 3)
            ->assertJsonPath('submissions.expected', 3)
            ->assertJsonPath('submissions.pct', 100);

        $this->data($this->manager, ['preset' => 'custom', 'from' => '2026-10-05', 'to' => '2026-10-05', 'user_id' => $this->otherAgent->id])
            ->assertJsonPath('submissions.expected', 0)
            ->assertJsonPath('submissions.pct', null);
    }

    public function test_attendance_section_adds_up_the_team_and_lists_the_most_missed_reports(): void
    {
        Holiday::createOrUpdateHoliday(['holiday_date' => '2026-10-04', 'title' => 'Govt. holiday']);

        // Friday the 2nd to Wednesday the 7th: an off day, a working day, a holiday, two working days and tomorrow
        // Agent One is OAPPPF and Agent Two OAHAPF, a report on a holiday still counts as present
        $response = $this->data($this->manager, ['preset' => 'custom', 'from' => '2026-10-02', 'to' => '2026-10-07', 'section' => 'attendance'])
            ->assertOk()
            ->assertJsonCount(6, 'daily')
            ->assertJsonPath('daily.2.holiday', 'Govt. holiday')
            ->assertJsonPath('summary.users', 2)
            ->assertJsonPath('summary.present', 4)
            ->assertJsonPath('summary.absent', 3)
            ->assertJsonPath('missing.total', 2)
            // the user who missed most comes first
            ->assertJsonPath('missing.rows.0.name', 'Agent Two')
            ->assertJsonPath('missing.rows.0.absent', 2)
            ->assertJsonPath('missing.rows.0.dates', ['2026-10-03', '2026-10-05']);

        $this->assertEquals(57.1, $response->json('summary.pct'));
        // Agent One reported on the 4th, 5th and 6th, Agent Two on the 6th only
        $this->assertSame([0, 0, 1, 1, 2, 0], array_column($response->json('daily'), 'present'));

        // days before a user was added are not held against them
        $newcomer = User::factory()->create(['usages_sector' => 'field', 'name' => 'Agent Three', 'created_at' => '2026-10-06 09:00:00']);
        $this->data($this->manager, ['preset' => 'custom', 'from' => '2026-10-02', 'to' => '2026-10-07', 'user_id' => $newcomer->id, 'section' => 'attendance'])
            ->assertJsonPath('summary.absent', 1);

        // a field user gets their own attendance, whoever they ask for
        $this->data($this->agent, ['preset' => 'custom', 'from' => '2026-10-02', 'to' => '2026-10-07', 'user_id' => $this->otherAgent->id, 'section' => 'attendance'])
            ->assertJsonPath('summary.users', 1)
            ->assertJsonPath('summary.present', 3);
    }

    public function test_live_section_shows_where_the_team_stands_today(): void
    {
        UserLeave::setForUsers([$this->otherAgent->id], ['2026-10-06'], ['portion' => 'full', 'type' => 'sick']);
        UserLeave::setForUsers([$this->agent->id], ['2026-10-07'], ['portion' => 'full', 'type' => 'sick'], null, UserLeave::STATUS_PENDING);

        // the 6th is a Tuesday: Agent One has reported, Agent Two is on leave
        $this->data($this->manager, ['section' => 'live'])
            ->assertOk()
            ->assertJsonPath('live.working_day', true)
            ->assertJsonPath('live.field_users', 2)
            ->assertJsonPath('live.reported', 2)
            ->assertJsonPath('live.on_leave', 1)
            ->assertJsonPath('live.due', 1)
            ->assertJsonPath('live.pending_leave_days', 1)
            ->assertJsonPath('live.checked_in', 0);
    }
    public function test_invalid_filters_are_rejected(): void
    {
        $this->data($this->manager, ['preset' => 'custom'])->assertStatus(422)->assertJsonValidationErrors(['from', 'to']);
        $this->data($this->manager, ['preset' => 'custom', 'from' => '2026-10-06', 'to' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->data($this->manager, ['preset' => 'custom', 'from' => '2024-01-01', 'to' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->data($this->manager, ['preset' => 'year'])->assertStatus(422)->assertJsonValidationErrors(['preset']);
        $this->data($this->manager, ['section' => 'everything'])->assertStatus(422)->assertJsonValidationErrors(['section']);
    }

    private function data(User $user, array $filters): TestResponse
    {
        return $this->actingAs($user)->getJson('/dashboard?'.http_build_query($filters), ['X-Requested-With' => 'XMLHttpRequest']);
    }

    private function report(User $user, string $date, int $outbound, int $replies, int $calls): DailyReport
    {
        return DailyReport::saveForUser($user, [
            'report_date' => $date,
            'outbound_calls' => $outbound,
            'inbound_calls' => 5,
            'order_processing' => 0,
            'platforms' => [['social_platform_id' => $this->platform->id, 'comments' => $replies]],
            'projects' => [['project_id' => $this->project->id, 'inbound_calls' => $calls]],
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\DailyTarget;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\SocialPlatform;
use App\Models\User;
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
            'platforms' => [['social_platform_id' => $this->platform->id, 'total_replies' => 10]],
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

        $users = collect($response->json('users'))->keyBy('name');
        $this->assertCount(2, $users);
        $this->assertSame(2, $users['Agent One']['reports']);
        $this->assertSame(70, $users['Agent One']['outbound_calls']);
        // 70 calls and 18 replies against targets of 100 and 20
        $this->assertSame(120, $users['Agent One']['target_total']);
        $this->assertEquals(73.3, $users['Agent One']['achievement_pct']);
        $this->assertSame('Agent One', $response->json('users.0.name'));
    }

    public function test_corporate_user_can_narrow_the_dashboard_to_one_user(): void
    {
        $response = $this->data($this->manager, ['preset' => 'today', 'user_id' => $this->otherAgent->id])
            ->assertOk()
            ->assertJsonPath('range.from', '2026-10-06')
            ->assertJsonPath('submissions.expected', 1)
            ->assertJsonCount(1, 'users');

        $this->assertSame(10, collect($response->json('kpis'))->firstWhere('key', 'outbound_calls')['actual']);
    }

    public function test_field_user_sees_only_their_own_reports_and_no_targets(): void
    {
        $response = $this->data($this->otherAgent, ['preset' => 'today', 'user_id' => $this->agent->id])
            ->assertOk()
            ->assertJsonPath('show_targets', false)
            ->assertJsonPath('users', null)
            ->assertJsonPath('platforms.0.actual', 3)
            ->assertJsonPath('platforms.0.target', null)
            ->assertJsonPath('submissions.expected', 1);

        $outbound = collect($response->json('kpis'))->firstWhere('key', 'outbound_calls');
        $this->assertSame(10, $outbound['actual']);
        $this->assertNull($outbound['target']);
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

    public function test_attendance_sheet_marks_each_day_of_each_field_user(): void
    {
        Holiday::createOrUpdateHoliday(['holiday_date' => '2026-10-04', 'title' => 'Govt. holiday']);

        // Friday the 2nd to Wednesday the 7th: an off day, a working day, a holiday, two working days and tomorrow
        $response = $this->data($this->manager, ['preset' => 'custom', 'from' => '2026-10-02', 'to' => '2026-10-07'])
            ->assertOk()
            ->assertJsonCount(6, 'attendance.days')
            ->assertJsonPath('attendance.days.2.holiday', 'Govt. holiday');

        $rows = collect($response->json('attendance.rows'))->keyBy('name');

        $this->assertCount(2, $rows);
        // a report sent on the holiday still counts as present
        $this->assertSame('OAPPPF', $rows['Agent One']['marks']);
        $this->assertSame(3, $rows['Agent One']['present']);
        $this->assertSame(1, $rows['Agent One']['absent']);
        $this->assertEquals(75, $rows['Agent One']['pct']);
        $this->assertSame('OAHAPF', $rows['Agent Two']['marks']);
        $this->assertSame(2, $rows['Agent Two']['absent']);

        // days before a user was added are not held against them
        $newcomer = User::factory()->create(['usages_sector' => 'field', 'name' => 'Agent Three', 'created_at' => '2026-10-06 09:00:00']);
        $row = collect($this->data($this->manager, ['preset' => 'custom', 'from' => '2026-10-02', 'to' => '2026-10-07', 'user_id' => $newcomer->id])
            ->json('attendance.rows'))->sole();

        $this->assertSame('ONHNAF', $row['marks']);
        $this->assertSame(1, $row['absent']);

        $this->data($this->agent, ['preset' => 'today'])->assertJsonPath('attendance', null);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->data($this->manager, ['preset' => 'custom'])->assertStatus(422)->assertJsonValidationErrors(['from', 'to']);
        $this->data($this->manager, ['preset' => 'custom', 'from' => '2026-10-06', 'to' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->data($this->manager, ['preset' => 'custom', 'from' => '2024-01-01', 'to' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->data($this->manager, ['preset' => 'year'])->assertStatus(422)->assertJsonValidationErrors(['preset']);
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
            'message_replies' => 8,
            'platforms' => [['social_platform_id' => $this->platform->id, 'total_replies' => $replies]],
            'projects' => [['project_id' => $this->project->id, 'total_calls' => $calls]],
        ]);
    }
}

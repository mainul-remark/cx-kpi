<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\DailyReport;
use App\Models\Holiday;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;
use Uzzal\Acl\Middleware\AuthenticateWithAcl;
use Uzzal\Acl\Middleware\ResourceMaker;

class AttendanceTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $agent;
    private User $otherAgent;

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

        $this->report($this->agent, '2026-10-04');
        $this->report($this->agent, '2026-10-05');
        $this->report($this->agent, '2026-10-06');
        $this->report($this->otherAgent, '2026-10-06');
    }

    public function test_attendance_page_offers_the_user_filter_to_corporate_users_only(): void
    {
        $this->actingAs($this->manager)->get('/attendance')
            ->assertOk()
            ->assertSee('id="attendance_user"', false)
            ->assertSee('Agent Two');

        $this->actingAs($this->agent)->get('/attendance')
            ->assertOk()
            ->assertDontSee('id="attendance_user"', false)
            ->assertDontSee('Agent Two');
    }

    public function test_a_report_on_a_day_marks_the_user_present(): void
    {
        Holiday::createOrUpdateHoliday(['holiday_date' => '2026-10-04', 'title' => 'Govt. holiday']);

        // Friday the 2nd to Wednesday the 7th: an off day, a working day, a holiday, two working days and tomorrow
        $response = $this->data($this->manager, ['preset' => 'custom', 'from' => '2026-10-02', 'to' => '2026-10-07'])
            ->assertOk()
            ->assertJsonPath('range.from', '2026-10-02')
            ->assertJsonPath('range.to', '2026-10-07')
            ->assertJsonCount(6, 'attendance.days');

        $rows = collect($response->json('attendance.rows'))->keyBy('name');

        $this->assertCount(2, $rows);
        $this->assertSame('OAPPPF', $rows['Agent One']['marks']);
        $this->assertSame(3, $rows['Agent One']['present']);
        $this->assertSame(1, $rows['Agent One']['absent']);
        $this->assertSame('OAHAPF', $rows['Agent Two']['marks']);
        $this->assertSame(1, $rows['Agent Two']['present']);
        $this->assertSame(2, $rows['Agent Two']['absent']);
    }

    public function test_a_full_day_of_leave_is_neither_present_nor_absent(): void
    {
        UserLeave::setForUsers([$this->otherAgent->id], ['2026-10-05', '2026-10-07'], ['portion' => 'full', 'type' => 'casual']);
        // a half day is still worked, so it stays an absence without a report
        UserLeave::setForUsers([$this->otherAgent->id], ['2026-10-03'], ['portion' => 'half', 'type' => 'casual']);

        // Friday the 2nd to Wednesday the 7th, which is tomorrow
        $response = $this->data($this->manager, ['preset' => 'custom', 'from' => '2026-10-02', 'to' => '2026-10-07'])->assertOk();

        $rows = collect($response->json('attendance.rows'))->keyBy('name');

        $this->assertSame('OAALPL', $rows['Agent Two']['marks']);
        $this->assertSame(1, $rows['Agent Two']['present']);
        $this->assertSame(2, $rows['Agent Two']['absent']);
        $this->assertSame(2, $rows['Agent Two']['leave']);
        $this->assertEquals(33.3, $rows['Agent Two']['pct']);
        $this->assertSame(0, $rows['Agent One']['leave']);
    }

    public function test_periods_cover_today_this_week_15_days_and_this_month(): void
    {
        $this->data($this->manager, ['preset' => 'today'])
            ->assertJsonPath('range.from', '2026-10-06')
            ->assertJsonPath('range.to', '2026-10-06')
            ->assertJsonCount(1, 'attendance.days');

        $this->data($this->manager, ['preset' => 'week'])->assertJsonPath('range.from', '2026-10-03');
        $this->data($this->manager, ['preset' => '15days'])->assertJsonPath('range.from', '2026-09-22')->assertJsonCount(15, 'attendance.days');
        $this->data($this->manager, ['preset' => 'month'])->assertJsonPath('range.from', '2026-10-01');
        $this->data($this->manager, [])->assertJsonPath('range.from', '2026-10-01');
    }

    public function test_corporate_user_can_narrow_the_sheet_to_one_user(): void
    {
        $this->data($this->manager, ['preset' => 'today', 'user_id' => $this->otherAgent->id])
            ->assertOk()
            ->assertJsonCount(1, 'attendance.rows')
            ->assertJsonPath('attendance.rows.0.name', 'Agent Two');
    }

    public function test_field_user_sees_only_their_own_attendance(): void
    {
        $this->data($this->otherAgent, ['preset' => 'today', 'user_id' => $this->agent->id])
            ->assertOk()
            ->assertJsonCount(1, 'attendance.rows')
            ->assertJsonPath('attendance.rows.0.name', 'Agent Two');
    }

    public function test_sheet_is_paged_on_the_server_with_totals_of_the_whole_sheet(): void
    {
        // 5 more field users, 7 in all, who all reported on the 6th
        foreach (range(1, 5) as $number) {
            $this->report(User::factory()->create(['usages_sector' => 'field', 'name' => 'Extra '.$number, 'created_at' => '2026-09-01 09:00:00']), '2026-10-06');
        }

        $filters = ['preset' => 'custom', 'from' => '2026-10-05', 'to' => '2026-10-06', 'per_page' => 5];

        $first = $this->data($this->manager, $filters)->assertOk();
        $first->assertJsonCount(5, 'attendance.rows')
            ->assertJsonPath('attendance.meta.page', 1)
            ->assertJsonPath('attendance.meta.total', 7)
            ->assertJsonPath('attendance.meta.last_page', 2)
            ->assertJsonPath('attendance.meta.from', 1)
            ->assertJsonPath('attendance.meta.to', 5)
            // the tiles add up every user, not only those on the page: 5 reports of the 6th, one more of the 5th
            ->assertJsonPath('attendance.summary.users', 7)
            ->assertJsonPath('attendance.summary.present', 8)
            ->assertJsonPath('attendance.summary.absent', 6);

        $names = collect($first->json('attendance.rows'))->pluck('name');
        $this->assertSame($names->sort()->values()->all(), $names->all());

        // the next page of the sheet only: the other users, and no totals when they are not asked for
        $second = $this->data($this->manager, $filters + ['section' => 'sheet', 'page' => 2, 'summary' => 0])->assertOk();
        $second->assertJsonCount(2, 'attendance.rows')
            ->assertJsonPath('attendance.meta.page', 2)
            ->assertJsonPath('attendance.meta.from', 6)
            ->assertJsonPath('attendance.meta.to', 7)
            ->assertJsonPath('attendance.summary', null)
            ->assertJsonMissingPath('check_ins')
            ->assertJsonMissingPath('currently_in');

        $this->assertEmpty($names->intersect(collect($second->json('attendance.rows'))->pluck('name')));
    }

    public function test_export_returns_every_row_of_the_sheet(): void
    {
        foreach (range(1, 5) as $number) {
            User::factory()->create(['usages_sector' => 'field', 'name' => 'Extra '.$number, 'created_at' => '2026-09-01 09:00:00']);
        }

        $this->data($this->manager, ['preset' => 'today', 'export' => 1, 'per_page' => 5])
            ->assertOk()
            ->assertJsonCount(7, 'attendance.rows')
            ->assertJsonMissingPath('check_ins')
            ->assertJsonMissingPath('attendance.meta');
    }

    public function test_check_in_log_is_paged_by_day_and_never_splits_a_day(): void
    {
        // four days, the third of them with two sessions
        foreach (['2026-10-03' => 1, '2026-10-04' => 1, '2026-10-05' => 2, '2026-10-06' => 1] as $date => $sessions) {
            foreach (range(1, $sessions) as $session) {
                AttendanceSession::create([
                    'user_id' => $this->agent->id,
                    'work_date' => $date,
                    'checked_in_at' => $date.' '.sprintf('%02d', 4 + $session).':00:00',
                    'checked_out_at' => $date.' '.sprintf('%02d', 5 + $session).':00:00',
                    'close_reason' => AttendanceSession::REASON_MANUAL,
                ]);
            }
        }

        $filters = ['preset' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-06', 'per_page' => 5, 'section' => 'check_ins'];

        // 5 is the least a page can hold, so the 4 days are on one page, newest first, with the two sessions in one row
        $one = $this->data($this->manager, $filters)->assertOk();
        $one->assertJsonCount(4, 'check_ins.rows')
            ->assertJsonPath('check_ins.meta.total', 4)
            ->assertJsonPath('check_ins.meta.last_page', 1)
            ->assertJsonMissingPath('attendance');
        $this->assertSame(['2026-10-06', '2026-10-05', '2026-10-04', '2026-10-03'], collect($one->json('check_ins.rows'))->pluck('work_date')->all());
        $this->assertSame([1, 2, 1, 1], collect($one->json('check_ins.rows'))->pluck('sessions')->all());

        // more days than a page holds: the second page carries on where the first stopped
        foreach (range(1, 4) as $number) {
            $other = User::factory()->create(['usages_sector' => 'field']);
            AttendanceSession::create([
                'user_id' => $other->id,
                'work_date' => '2026-10-06',
                'checked_in_at' => '2026-10-06 0'.$number.':00:00',
                'checked_out_at' => null,
            ]);
        }

        $pages = collect([1, 2])->map(fn (int $page) => $this->data($this->manager, $filters + ['page' => $page])->assertOk());

        $pages[0]->assertJsonCount(5, 'check_ins.rows')->assertJsonPath('check_ins.meta.total', 8)->assertJsonPath('check_ins.meta.last_page', 2);
        $pages[1]->assertJsonCount(3, 'check_ins.rows')->assertJsonPath('check_ins.meta.from', 6)->assertJsonPath('check_ins.meta.to', 8);

        $keys = $pages->flatMap(fn ($page) => $page->json('check_ins.rows'))->map(fn (array $row) => $row['user_id'].'|'.$row['work_date']);
        $this->assertCount(8, $keys->unique());
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
        return $this->actingAs($user)->getJson('/attendance?'.http_build_query($filters), ['X-Requested-With' => 'XMLHttpRequest']);
    }

    private function report(User $user, string $date): DailyReport
    {
        return DailyReport::saveForUser($user, [
            'report_date' => $date,
            'outbound_calls' => 10,
            'inbound_calls' => 5,
            'order_processing' => 0,
        ]);
    }
}

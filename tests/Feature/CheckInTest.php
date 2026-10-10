<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Uzzal\Acl\Middleware\AuthenticateWithAcl;
use Uzzal\Acl\Middleware\ResourceMaker;

class CheckInTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(array_values(array_filter([
            config('jetstream.auth_session'),
            EnsureEmailIsVerified::class,
            ResourceMaker::class,
            AuthenticateWithAcl::class,
        ])));

        config(['attendance.timezone' => 'Asia/Dhaka']);
        Carbon::setTestNow('2026-10-06 04:00:00'); // 10:00 in Dhaka

        $this->agent = User::factory()->create(['usages_sector' => 'field']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_checked_in_user_is_sent_to_the_report_form_until_it_is_filed(): void
    {
        AttendanceSession::create([
            'user_id' => $this->agent->id,
            'work_date' => '2026-10-05',
            'checked_in_at' => '2026-10-05 06:08:12',
            'checked_out_at' => null,
            'auto_closed_at' => '2026-10-06 03:00:00',
            'close_reason' => AttendanceSession::REASON_AUTO,
        ]);

        $this->actingAs($this->agent)->get('/dashboard')->assertRedirect(route('daily-reports.create'));
        $this->actingAs($this->agent)->get(route('daily-reports.create'))->assertOk();

        \DB::table('daily_reports')->insert([
            'user_id' => $this->agent->id, 'report_date' => '2026-10-06',
            'outbound_calls' => 0, 'inbound_calls' => 0, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($this->agent)->get('/dashboard')->assertSuccessful();
    }

    public function test_filing_a_report_ends_a_forgotten_session_at_end_of_day(): void
    {
        $session = AttendanceSession::create([
            'user_id' => $this->agent->id,
            'work_date' => '2026-10-05',
            'checked_in_at' => '2026-10-05 06:08:12',
            'checked_out_at' => null,
            'auto_closed_at' => '2026-10-06 03:00:00',
            'close_reason' => AttendanceSession::REASON_AUTO,
        ]);

        app(\App\Services\Attendance\AttendanceService::class)->finalizeForgotten($this->agent);

        $session->refresh();
        // 11:59 pm in Dhaka on the day itself
        $this->assertSame('2026-10-05 23:59', $session->checked_out_at->copy()->timezone('Asia/Dhaka')->format('Y-m-d H:i'));
        $this->assertSame(AttendanceSession::REASON_AUTO, $session->close_reason);

        $row = app(\App\Services\Attendance\AttendanceService::class)->sessions('2026-10-01', '2026-10-31', $this->agent->id, false)['rows'][0];
        $this->assertSame('incomplete', $row['status']);
        $this->assertNull($row['minutes']);
    }

    public function test_field_user_checks_in_and_out(): void
    {
        $this->actingAs($this->agent)->postJson('/attendance/check-in', ['lat' => 23.78, 'lng' => 90.41, 'accuracy' => 12])
            ->assertOk()
            ->assertJsonPath('checked_in', true);

        $session = AttendanceSession::first();
        $this->assertSame('2026-10-06', $session->work_date->toDateString());
        $this->assertEquals(23.78, (float) $session->check_in_lat);

        Carbon::setTestNow('2026-10-06 12:00:00');
        $this->actingAs($this->agent)->postJson('/attendance/check-out')
            ->assertOk()
            ->assertJsonPath('checked_in', false);

        $session->refresh();
        $this->assertSame(AttendanceSession::REASON_MANUAL, $session->close_reason);
        $this->assertNotNull($session->checked_out_at);
        $this->assertNull($session->check_out_lat);
    }

    public function test_location_is_optional(): void
    {
        $this->actingAs($this->agent)->postJson('/attendance/check-in')->assertOk();

        $this->assertNull(AttendanceSession::first()->check_in_lat);
    }

    public function test_invalid_location_is_rejected_but_missing_is_fine(): void
    {
        $this->actingAs($this->agent)->postJson('/attendance/check-in', ['lat' => 200, 'lng' => 90])
            ->assertStatus(422);
    }

    public function test_checking_in_twice_keeps_one_open_session(): void
    {
        $this->actingAs($this->agent)->postJson('/attendance/check-in')->assertOk();
        $this->actingAs($this->agent)->postJson('/attendance/check-in')
            ->assertOk()
            ->assertJsonPath('message', 'You are already checked in.');

        $this->assertSame(1, AttendanceSession::count());
    }

    public function test_the_database_refuses_a_second_open_session(): void
    {
        $this->actingAs($this->agent)->postJson('/attendance/check-in')->assertOk();

        $this->expectException(\Illuminate\Database\QueryException::class);

        AttendanceSession::create([
            'user_id' => $this->agent->id,
            'work_date' => '2026-10-06',
            'checked_in_at' => now(),
        ]);
    }

    public function test_checking_out_without_a_session_changes_nothing(): void
    {
        $this->actingAs($this->agent)->postJson('/attendance/check-out')
            ->assertOk()
            ->assertJsonPath('message', 'You are not checked in.');

        $this->assertSame(0, AttendanceSession::count());
    }

    public function test_corporate_user_cannot_check_in(): void
    {
        $manager = User::factory()->create(['usages_sector' => 'corporate']);

        $this->actingAs($manager)->postJson('/attendance/check-in')->assertForbidden();
        $this->actingAs($manager)->getJson('/attendance/status')->assertForbidden();
    }

    public function test_forgotten_session_is_closed_after_midnight_without_a_checkout_time(): void
    {
        $this->actingAs($this->agent)->postJson('/attendance/check-in')->assertOk();

        // 00:30 in Dhaka the next day
        Carbon::setTestNow('2026-10-06 18:30:00');
        $this->artisan('attendance:auto-close')->assertSuccessful();

        $session = AttendanceSession::first();
        $this->assertSame(AttendanceSession::REASON_AUTO, $session->close_reason);
        $this->assertNotNull($session->auto_closed_at);
        $this->assertNull($session->checked_out_at);
        $this->assertNull($session->acknowledged_at);
    }

    public function test_session_still_inside_the_work_day_is_not_closed(): void
    {
        $this->actingAs($this->agent)->postJson('/attendance/check-in')->assertOk();

        // 23:30 in Dhaka the same day
        Carbon::setTestNow('2026-10-06 17:30:00');
        $this->artisan('attendance:auto-close')->assertSuccessful();

        $this->assertNull(AttendanceSession::first()->auto_closed_at);
    }

    public function test_user_is_warned_once_about_the_forgotten_checkout(): void
    {
        $this->actingAs($this->agent)->postJson('/attendance/check-in')->assertOk();
        Carbon::setTestNow('2026-10-06 18:30:00');
        $this->artisan('attendance:auto-close');

        $this->actingAs($this->agent)->getJson('/attendance/status')
            ->assertOk()
            ->assertJsonPath('checked_in', false)
            ->assertJsonPath('warnings', ['06 Oct 2026']);

        // checking in shows it again, then it is acknowledged
        $this->actingAs($this->agent)->postJson('/attendance/check-in')
            ->assertOk()
            ->assertJsonPath('checked_in', true)
            ->assertJsonPath('warnings', ['06 Oct 2026']);

        $this->actingAs($this->agent)->getJson('/attendance/status')->assertJsonPath('warnings', []);
    }

    public function test_check_in_closes_a_stale_session_even_when_the_scheduler_did_not_run(): void
    {
        $this->actingAs($this->agent)->postJson('/attendance/check-in')->assertOk();

        Carbon::setTestNow('2026-10-07 04:00:00');
        $this->actingAs($this->agent)->postJson('/attendance/check-in')
            ->assertOk()
            ->assertJsonPath('warnings', ['06 Oct 2026']);

        $this->assertSame(2, AttendanceSession::count());
        $this->assertSame(1, AttendanceSession::forgotten()->count());
        $this->assertSame(1, AttendanceSession::open()->count());
    }

    public function test_dismissing_the_warning_acknowledges_it(): void
    {
        $this->actingAs($this->agent)->postJson('/attendance/check-in');
        Carbon::setTestNow('2026-10-06 18:30:00');
        $this->artisan('attendance:auto-close');

        $this->actingAs($this->agent)->postJson('/attendance/acknowledge')->assertOk();

        $this->actingAs($this->agent)->getJson('/attendance/status')->assertJsonPath('warnings', []);
    }

    public function test_header_shows_the_button_to_field_users_only(): void
    {
        $this->actingAs($this->agent)->get('/dashboard')->assertSee('id="checkInButton"', false);

        $manager = User::factory()->create(['usages_sector' => 'corporate']);
        $this->actingAs($manager)->get('/dashboard')->assertDontSee('id="checkInButton"', false);
    }

    public function test_a_report_or_a_check_in_makes_a_user_present(): void
    {
        $manager = User::factory()->create(['usages_sector' => 'corporate']);
        $reporter = User::factory()->create(['usages_sector' => 'field', 'name' => 'Reporter', 'created_at' => '2026-09-01 09:00:00']);
        $this->agent->update(['created_at' => '2026-09-01 09:00:00', 'name' => 'Checker']);

        // one user only files a daily report, the other only checks in and forgets to check out: both are present
        \DB::table('daily_reports')->insert(['user_id' => $reporter->id, 'report_date' => '2026-10-06', 'created_at' => now(), 'updated_at' => now()]);
        $this->actingAs($this->agent)->postJson('/attendance/check-in')->assertOk();
        Carbon::setTestNow('2026-10-06 18:30:00');
        $this->artisan('attendance:auto-close');

        $data = $this->actingAs($manager)->getJson('/attendance?preset=custom&from=2026-10-06&to=2026-10-06', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json();
        $rows = collect($data['attendance']['rows'])->keyBy('name');

        $this->assertSame('P', $rows['Reporter']['marks']);
        $this->assertSame(0, $rows['Reporter']['checked_in']);
        $this->assertSame('P', $rows['Checker']['marks']);
        $this->assertSame(1, $rows['Checker']['checked_in']);
        $this->assertSame(1, $rows['Checker']['incomplete']);
        $row = $data;
        $this->assertSame('incomplete', $row['check_ins']['rows'][0]['status']);
        $this->assertNull($row['check_ins']['rows'][0]['checked_out']);
        $this->assertSame([], $row['currently_in']);
    }

    public function test_manager_sees_who_is_checked_in_but_a_field_user_sees_only_their_own_log(): void
    {
        $manager = User::factory()->create(['usages_sector' => 'corporate']);
        $other = User::factory()->create(['usages_sector' => 'field']);
        $this->actingAs($this->agent)->postJson('/attendance/check-in');
        $this->actingAs($other)->postJson('/attendance/check-in');

        $this->actingAs($manager)->getJson('/attendance?preset=today', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertJsonCount(2, 'currently_in')
            ->assertJsonCount(2, 'check_ins.rows');

        $own = $this->actingAs($this->agent)->getJson('/attendance?preset=today', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertJsonCount(0, 'currently_in')
            ->assertJsonCount(1, 'check_ins.rows')
            ->json('check_ins.rows.0');

        $this->assertArrayNotHasKey('in_location', $own);
    }

    public function test_several_sessions_of_a_day_show_as_one_row_with_first_in_and_last_out(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $this->actingAs($this->agent)->postJson('/attendance/check-in');
        $this->travelTo(now()->setTime(10, 0));
        $this->actingAs($this->agent)->postJson('/attendance/check-out');
        $this->travelTo(now()->setTime(11, 0));
        $this->actingAs($this->agent)->postJson('/attendance/check-in');
        $this->travelTo(now()->setTime(12, 30));
        $this->actingAs($this->agent)->postJson('/attendance/check-out');

        $rows = $this->actingAs($this->agent)->getJson('/attendance?preset=today', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertJsonCount(1, 'check_ins.rows')
            ->json('check_ins.rows');

        $this->assertSame(2, $rows[0]['sessions']);
        $this->assertSame(150, $rows[0]['minutes']);
        $this->assertSame('completed', $rows[0]['status']);
        $this->assertNotSame($rows[0]['checked_in'], $rows[0]['checked_out']);
    }

    public function test_manager_corrects_a_forgotten_checkout(): void
    {
        $manager = User::factory()->create(['usages_sector' => 'corporate']);
        $this->actingAs($this->agent)->postJson('/attendance/check-in');
        Carbon::setTestNow('2026-10-07 04:00:00');
        $this->artisan('attendance:auto-close');
        $session = AttendanceSession::first();

        // 18:00 in Dhaka the day it was forgotten
        $this->actingAs($manager)->postJson("/attendance/sessions/{$session->id}/adjust", [
            'checked_out_at' => '2026-10-06T18:00',
            'note' => 'Confirmed by phone',
        ])->assertOk();

        $session->refresh();
        $this->assertSame(AttendanceSession::REASON_ADMIN, $session->close_reason);
        $this->assertSame('2026-10-06 12:00:00', $session->checked_out_at->format('Y-m-d H:i:s'));
        $this->assertSame($manager->id, $session->adjusted_by);

        // a corrected session no longer warns the user
        $this->actingAs($this->agent)->getJson('/attendance/status')->assertJsonPath('warnings', []);
    }

    public function test_a_correction_cannot_precede_the_check_in_or_come_from_a_field_user(): void
    {
        $manager = User::factory()->create(['usages_sector' => 'corporate']);
        $this->actingAs($this->agent)->postJson('/attendance/check-in');
        $session = AttendanceSession::first();

        $this->actingAs($manager)->postJson("/attendance/sessions/{$session->id}/adjust", [
            'checked_out_at' => '2026-10-06T08:00',
            'note' => 'Too early',
        ])->assertStatus(422)->assertJsonValidationErrors(['checked_out_at']);

        $this->actingAs($this->agent)->postJson("/attendance/sessions/{$session->id}/adjust", [
            'checked_out_at' => '2026-10-06T15:00',
            'note' => 'Self service',
        ])->assertForbidden();
    }

    public function test_reminder_goes_to_users_still_checked_in_and_clears_on_checkout(): void
    {
        $other = User::factory()->create(['usages_sector' => 'field']);
        $this->actingAs($this->agent)->postJson('/attendance/check-in');
        $this->actingAs($other)->postJson('/attendance/check-in');
        $this->actingAs($other)->postJson('/attendance/check-out');

        $this->artisan('attendance:remind')->assertSuccessful();

        $this->assertSame(1, $this->agent->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());

        $this->actingAs($this->agent)->getJson('/attendance/status')->assertJsonPath('reminder', true);

        $this->actingAs($this->agent)->postJson('/attendance/check-out');
        $this->actingAs($this->agent)->getJson('/attendance/status')->assertJsonPath('reminder', false);
    }

    public function test_the_kpi_counts_checked_in_and_incomplete_days_without_changing_the_score(): void
    {
        Carbon::setTestNow('2026-10-04 05:00:00');
        $this->actingAs($this->agent)->postJson('/attendance/check-in')->assertOk();
        Carbon::setTestNow('2026-10-05 05:00:00');
        $this->actingAs($this->agent)->postJson('/attendance/check-out');
        $this->actingAs($this->agent)->postJson('/attendance/check-in')->assertOk();
        Carbon::setTestNow('2026-10-06 04:00:00');
        $this->artisan('attendance:auto-close');

        $row = app(\App\Services\Kpi\EmployeeKpiService::class)->forUsers(collect([$this->agent]), '2026-10-01', '2026-10-06')->first();

        $this->assertSame(2, $row['checked_in_days']);
        $this->assertSame(2, $row['incomplete_days']);
        $this->assertNull($row['score']);
    }
}

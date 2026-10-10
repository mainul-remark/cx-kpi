<?php

namespace Tests\Feature;

use App\Models\AttendanceSession;
use App\Models\Holiday;
use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\UserLeave;
use App\Models\WorkShift;
use App\Services\Kpi\EmployeeKpiService;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Uzzal\Acl\Middleware\AuthenticateWithAcl;
use Uzzal\Acl\Middleware\ResourceMaker;

class AttendanceRulesTest extends TestCase
{
    use RefreshDatabase;

    private User $agent;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(array_values(array_filter([
            config('jetstream.auth_session'),
            EnsureEmailIsVerified::class,
            ResourceMaker::class,
            AuthenticateWithAcl::class,
        ])));

        config([
            'attendance.timezone' => 'Asia/Dhaka',
            'attendance.default_shift' => ['start_time' => '10:00', 'grace_minutes' => 15],
            'attendance.require_office' => false,
        ]);

        // a Tuesday; Friday is the weekly off day
        $this->at('2026-10-06 04:00:00');

        $this->agent = User::factory()->create(['usages_sector' => 'field', 'created_at' => '2026-09-01 09:00:00']);
        $this->manager = User::factory()->create(['usages_sector' => 'corporate']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function at(string $utc): void
    {
        Carbon::setTestNow($utc);
    }

    private function checkIn(array $geo = []): AttendanceSession
    {
        $this->actingAs($this->agent)->postJson('/attendance/check-in', $geo)->assertOk();

        return AttendanceSession::latest('id')->first();
    }

    public function test_a_check_in_inside_the_grace_is_on_time_and_after_it_is_late(): void
    {
        $this->at('2026-10-06 04:15:00'); // 10:15 in Dhaka, the end of the grace
        $this->assertSame(0, $this->checkIn()->late_minutes);

        $this->actingAs($this->agent)->postJson('/attendance/check-out');
        $this->at('2026-10-07 04:20:00'); // 10:20 the next day
        $this->assertSame(20, $this->checkIn()->late_minutes);
    }

    public function test_only_the_first_check_in_of_a_day_is_judged(): void
    {
        $this->at('2026-10-06 03:00:00'); // 09:00
        $this->assertSame(0, $this->checkIn()->late_minutes);
        $this->actingAs($this->agent)->postJson('/attendance/check-out');

        $this->at('2026-10-06 08:00:00'); // 14:00, back from a visit
        $this->assertNull($this->checkIn()->late_minutes);
    }

    public function test_off_days_holidays_and_leave_are_not_judged(): void
    {
        $this->at('2026-10-09 06:00:00'); // Friday, the weekly off day, 12:00
        $this->assertNull($this->checkIn()->late_minutes);
        $this->actingAs($this->agent)->postJson('/attendance/check-out');

        Holiday::createOrUpdateHoliday(['holiday_date' => '2026-10-10', 'title' => 'Holiday']);
        $this->at('2026-10-10 06:00:00');
        $this->assertNull($this->checkIn()->late_minutes);
        $this->actingAs($this->agent)->postJson('/attendance/check-out');

        UserLeave::setForUsers([$this->agent->id], ['2026-10-11'], ['portion' => 'half', 'type' => 'casual']);
        $this->at('2026-10-11 06:00:00');
        $this->assertNull($this->checkIn()->late_minutes);
    }

    public function test_the_shift_of_the_user_sets_the_start_and_grace(): void
    {
        $shift = WorkShift::create(['name' => 'Late shift', 'start_time' => '12:00', 'grace_minutes' => 0]);
        $this->agent->forceFill(['work_shift_id' => $shift->id])->save();

        $this->at('2026-10-06 05:00:00'); // 11:00, early for this shift
        $this->assertSame(0, $this->checkIn()->late_minutes);
        $this->actingAs($this->agent)->postJson('/attendance/check-out');

        $this->at('2026-10-07 06:10:00'); // 12:10, past a shift without grace
        $this->assertSame(10, $this->checkIn()->late_minutes);
    }

    public function test_the_place_of_a_check_in_is_told_by_position_or_address(): void
    {
        OfficeLocation::create(['name' => 'Head office', 'lat' => 23.7800000, 'lng' => 90.4100000, 'radius_m' => 200, 'allowed_ips' => '203.0.113.0/24']);

        $session = $this->checkIn(['lat' => 23.7805, 'lng' => 90.4102]);
        $this->assertSame('office', $session->check_in_place);
        $this->assertNotNull($session->office_location_id);
        $this->actingAs($this->agent)->postJson('/attendance/check-out');

        $this->assertSame('field', $this->checkIn(['lat' => 23.9, 'lng' => 90.6])->check_in_place);
        $this->actingAs($this->agent)->postJson('/attendance/check-out');

        $this->assertSame('unknown', $this->checkIn()->check_in_place);
        $this->actingAs($this->agent)->postJson('/attendance/check-out');

        $this->actingAs($this->agent)->withServerVariables(['REMOTE_ADDR' => '203.0.113.9'])->postJson('/attendance/check-in')->assertOk();
        $this->assertSame('office', AttendanceSession::latest('id')->first()->check_in_place);
    }

    public function test_check_ins_can_be_limited_to_the_offices(): void
    {
        config(['attendance.require_office' => true]);
        OfficeLocation::create(['name' => 'Head office', 'lat' => 23.78, 'lng' => 90.41, 'radius_m' => 200]);

        $this->actingAs($this->agent)->postJson('/attendance/check-in', ['lat' => 23.9, 'lng' => 90.6])
            ->assertStatus(422)->assertJsonValidationErrors(['location']);
        $this->assertSame(0, AttendanceSession::count());

        $this->actingAs($this->agent)->postJson('/attendance/check-in', ['lat' => 23.7801, 'lng' => 90.4101])->assertOk();
        $this->assertSame(1, AttendanceSession::count());
    }

    public function test_an_inactive_office_is_ignored(): void
    {
        OfficeLocation::create(['name' => 'Closed', 'lat' => 23.78, 'lng' => 90.41, 'radius_m' => 200, 'is_active' => false]);

        $this->assertSame('field', $this->checkIn(['lat' => 23.78, 'lng' => 90.41])->check_in_place);
    }

    public function test_a_manager_manages_shifts_and_assigns_users(): void
    {
        $this->actingAs($this->manager)->get('/attendance-settings')->assertOk();

        $this->actingAs($this->manager)->postJson('/attendance-settings/shifts', ['name' => 'Morning', 'start_time' => '09:00', 'grace_minutes' => 10])->assertOk();
        $shift = WorkShift::first();

        $this->actingAs($this->manager)->postJson('/attendance-settings/shifts', ['name' => 'Morning', 'start_time' => '09:00', 'grace_minutes' => 10])
            ->assertStatus(422)->assertJsonValidationErrors(['name']);
        $this->actingAs($this->manager)->postJson('/attendance-settings/shifts', ['name' => 'Bad', 'start_time' => '9am', 'grace_minutes' => 10])
            ->assertStatus(422)->assertJsonValidationErrors(['start_time']);

        $this->actingAs($this->manager)->postJson("/attendance-settings/shifts/{$shift->id}/users", ['user_ids' => [$this->agent->id]])->assertOk();
        $this->assertSame($shift->id, $this->agent->fresh()->work_shift_id);

        $this->actingAs($this->manager)->postJson("/attendance-settings/shifts/{$shift->id}/users", ['user_ids' => []])->assertOk();
        $this->assertNull($this->agent->fresh()->work_shift_id);

        // a corporate user cannot be put on a shift
        $this->actingAs($this->manager)->postJson("/attendance-settings/shifts/{$shift->id}/users", ['user_ids' => [$this->manager->id]])
            ->assertStatus(422);

        $this->agent->forceFill(['work_shift_id' => $shift->id])->save();
        $this->actingAs($this->manager)->deleteJson("/attendance-settings/shifts/{$shift->id}")->assertOk();
        $this->assertNull($this->agent->fresh()->work_shift_id);
    }

    public function test_office_locations_are_validated(): void
    {
        $this->actingAs($this->manager)->postJson('/attendance-settings/offices', ['name' => 'HQ', 'lat' => 23.78, 'lng' => 90.41, 'radius_m' => 150, 'allowed_ips' => '203.0.113.5, 10.0.0.0/8'])
            ->assertOk();
        $this->assertSame('203.0.113.5, 10.0.0.0/8', OfficeLocation::first()->allowed_ips);

        $this->actingAs($this->manager)->postJson('/attendance-settings/offices', ['name' => 'Nowhere', 'radius_m' => 150])
            ->assertStatus(422)->assertJsonValidationErrors(['lat']);
        $this->actingAs($this->manager)->postJson('/attendance-settings/offices', ['name' => 'Bad ip', 'radius_m' => 150, 'allowed_ips' => 'not-an-ip'])
            ->assertStatus(422)->assertJsonValidationErrors(['allowed_ips']);
        $this->actingAs($this->manager)->postJson('/attendance-settings/offices', ['name' => 'Half', 'lat' => 23.78, 'radius_m' => 150])
            ->assertStatus(422)->assertJsonValidationErrors(['lng']);
    }

    public function test_field_users_cannot_open_or_change_the_settings(): void
    {
        $this->actingAs($this->agent)->get('/attendance-settings')->assertForbidden();
        $this->actingAs($this->agent)->postJson('/attendance-settings/shifts', ['name' => 'X', 'start_time' => '09:00', 'grace_minutes' => 0])->assertForbidden();
        $this->actingAs($this->agent)->postJson('/attendance-settings/offices', ['name' => 'X', 'lat' => 1, 'lng' => 1, 'radius_m' => 100])->assertForbidden();
    }

    public function test_the_kpi_reports_late_and_incomplete_days_without_changing_the_score(): void
    {
        $this->at('2026-10-04 05:00:00'); // Sunday 11:00, late
        $this->checkIn();
        $this->at('2026-10-05 05:00:00'); // Monday 11:00, late; Sunday was never checked out of, and Monday is not either
        $this->actingAs($this->agent)->postJson('/attendance/check-out');
        $this->checkIn();
        $this->at('2026-10-06 04:00:00'); // Tuesday 10:00
        $this->artisan('attendance:auto-close');

        $row = app(EmployeeKpiService::class)->forUsers(collect([$this->agent]), '2026-10-01', '2026-10-06')->first();

        $this->assertSame(2, $row['checked_in_days']);
        $this->assertSame(2, $row['late_days']);
        $this->assertSame(2, $row['incomplete_days']);
        $this->assertNull($row['score']);
    }
}

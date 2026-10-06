<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\Holiday;
use App\Models\User;
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
            'message_replies' => 8,
        ]);
    }
}

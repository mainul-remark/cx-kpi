<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Uzzal\Acl\Middleware\AuthenticateWithAcl;
use Uzzal\Acl\Middleware\ResourceMaker;

class LeaveRequestTest extends TestCase
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

        // a Tuesday, so the week in progress runs from Saturday the 3rd to Friday the 9th
        Carbon::setTestNow('2026-10-06 10:00:00');

        $this->manager = User::factory()->create(['usages_sector' => 'corporate']);
        $this->agent = User::factory()->create(['usages_sector' => 'field', 'name' => 'Agent One', 'created_at' => '2026-09-01 09:00:00']);
        $this->otherAgent = User::factory()->create(['usages_sector' => 'field', 'name' => 'Agent Two', 'created_at' => '2026-09-01 09:00:00']);
    }

    public function test_field_user_asks_for_leave_which_waits_as_pending(): void
    {
        // Thursday the 8th to Saturday the 10th, with the Friday skipped
        $this->actingAs($this->agent)->postJson('/my-leaves', $this->payload('2026-10-08', '2026-10-10'))
            ->assertCreated()
            ->assertJsonPath('data.dates', ['2026-10-08', '2026-10-10']);

        $this->assertDatabaseCount('user_leaves', 2);
        $leave = UserLeave::query()->whereDate('leave_date', '2026-10-08')->firstOrFail();

        $this->assertSame($this->agent->id, $leave->user_id);
        $this->assertSame('pending', $leave->status);
        $this->assertNull($leave->approved_by);

        $this->actingAs($this->agent)->get('/my-leaves')->assertOk()->assertSee('id="myLeaveForm"', false);

        $this->actingAs($this->agent)
            ->getJson('/my-leaves?draw=1&start=0&length=10', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 2)
            ->assertJsonPath('data.0.leave_date', '2026-10-10')
            ->assertJsonPath('data.0.status', 'pending');

        // the leave of someone else is not listed
        $this->actingAs($this->otherAgent)
            ->getJson('/my-leaves?draw=1&start=0&length=10', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertJsonPath('recordsTotal', 0);
    }

    public function test_pending_leave_does_not_count_until_it_is_approved(): void
    {
        $this->actingAs($this->agent)->postJson('/my-leaves', $this->payload('2026-10-05', '2026-10-05'))->assertCreated();
        $leave = UserLeave::query()->firstOrFail();

        $marks = fn () => collect($this->actingAs($this->manager)
            ->getJson('/attendance?preset=custom&from=2026-10-05&to=2026-10-05', ['X-Requested-With' => 'XMLHttpRequest'])
            ->json('attendance.rows'))->keyBy('name')['Agent One']['marks'];

        $this->assertSame('A', $marks());

        $this->actingAs($this->manager)->postJson("/leaves/{$leave->id}/approve")
            ->assertOk()
            ->assertJsonPath('data.status', 'approved');

        $this->assertSame($this->manager->id, $leave->fresh()->approved_by);
        $this->assertSame('L', $marks());

        // approved leave blocks a report, a rejected one does not: the report is filed under the day it is sent on
        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->actingAs($this->agent)->postJson('/daily-reports', $this->reportPayload('2026-10-05'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['outbound_calls']);

        $this->actingAs($this->manager)->postJson("/leaves/{$leave->id}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected');

        $this->assertSame('A', $marks());
        $this->actingAs($this->agent)->postJson('/daily-reports', $this->reportPayload('2026-10-05'))->assertCreated();

        // now that the day is reported on, the full day can no longer be approved
        $this->actingAs($this->manager)->postJson("/leaves/{$leave->id}/approve")->assertStatus(422);
        $this->assertSame('rejected', $leave->fresh()->status);

        $this->actingAs($this->manager)
            ->getJson('/leaves?draw=1&start=0&length=10&status=rejected', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertJsonPath('recordsTotal', 1);
        $this->actingAs($this->manager)
            ->getJson('/leaves?draw=1&start=0&length=10&status=pending', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertJsonPath('recordsTotal', 0);
    }

    public function test_request_is_checked_against_approved_leave_reports_and_off_days(): void
    {
        UserLeave::setForUsers([$this->agent->id], ['2026-10-07'], ['portion' => 'full', 'type' => 'casual'], $this->manager->id);
        DailyReport::saveForUser($this->agent, $this->reportPayload('2026-10-06'));

        $this->actingAs($this->agent);

        // over a day already approved, over a day reported on, and on a Friday
        $this->postJson('/my-leaves', $this->payload('2026-10-07', '2026-10-08'))->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->postJson('/my-leaves', $this->payload('2026-10-06', '2026-10-06'))->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->postJson('/my-leaves', $this->payload('2026-10-09', '2026-10-09'))->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->postJson('/my-leaves', $this->payload('2026-10-08', '2026-12-08'))->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->postJson('/my-leaves', ['portion' => 'quarter'] + $this->payload('2026-10-08', '2026-10-08'))->assertStatus(422)->assertJsonValidationErrors(['portion']);

        $this->assertDatabaseCount('user_leaves', 1);

        // a half day is still worked, so it can be asked for on a day reported on
        $this->postJson('/my-leaves', ['portion' => 'half'] + $this->payload('2026-10-06', '2026-10-06'))->assertCreated();

        // asking again for a day still pending replaces the request
        $this->postJson('/my-leaves', ['portion' => 'half', 'type' => 'sick'] + $this->payload('2026-10-06', '2026-10-06'))->assertCreated();
        $this->assertDatabaseCount('user_leaves', 2);
        $this->assertSame('sick', UserLeave::query()->whereDate('leave_date', '2026-10-06')->value('type'));

        // leave is kept for the users who report
        $this->actingAs($this->manager)->postJson('/my-leaves', $this->payload('2026-10-08', '2026-10-08'))->assertForbidden();
    }

    public function test_only_an_own_pending_request_can_be_withdrawn(): void
    {
        $this->actingAs($this->agent)->postJson('/my-leaves', $this->payload('2026-10-07', '2026-10-08'))->assertCreated();
        [$first, $second] = UserLeave::query()->orderBy('leave_date')->get()->all();

        $this->actingAs($this->otherAgent)->deleteJson("/my-leaves/{$first->id}")->assertForbidden();

        $this->actingAs($this->manager)->postJson("/leaves/{$second->id}/approve")->assertOk();
        $this->actingAs($this->agent)->deleteJson("/my-leaves/{$second->id}")->assertStatus(422);

        $this->actingAs($this->agent)->deleteJson("/my-leaves/{$first->id}")->assertOk();

        $this->assertDatabaseCount('user_leaves', 1);
        $this->assertDatabaseHas('user_leaves', ['id' => $second->id, 'status' => 'approved']);
    }

    public function test_leave_set_by_an_admin_is_approved_at_once_and_takes_over_a_request(): void
    {
        $this->actingAs($this->agent)->postJson('/my-leaves', $this->payload('2026-10-07', '2026-10-07'))->assertCreated();

        $this->actingAs($this->manager)->postJson('/leaves', ['user_ids' => [$this->agent->id]] + $this->payload('2026-10-07', '2026-10-07'))->assertCreated();

        $this->assertDatabaseCount('user_leaves', 1);
        $leave = UserLeave::query()->firstOrFail();

        $this->assertSame('approved', $leave->status);
        $this->assertSame($this->manager->id, $leave->approved_by);
    }

    private function payload(string $from, string $to): array
    {
        return [
            'from' => $from,
            'to' => $to,
            'portion' => 'full',
            'type' => 'casual',
            'note' => 'Family event',
        ];
    }

    private function reportPayload(string $date): array
    {
        return [
            'report_date' => $date,
            'outbound_calls' => 40,
            'inbound_calls' => 15,
            'order_processing' => 0,
        ];
    }
}

<?php

namespace Tests\Feature;

use App\Models\DailyReport;
use App\Models\DailyTarget;
use App\Models\Holiday;
use App\Models\User;
use App\Models\UserLeave;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Uzzal\Acl\Middleware\AuthenticateWithAcl;
use Uzzal\Acl\Middleware\ResourceMaker;

class UserLeaveTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;

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
        $this->actingAs($this->manager);
    }

    public function test_leave_is_set_for_each_user_on_each_working_day(): void
    {
        $users = $this->fieldUsers(2);
        Holiday::createOrUpdateHoliday(['holiday_date' => '2026-10-07', 'title' => 'Govt. holiday']);

        // Tuesday the 6th to Saturday the 10th: Wednesday is a holiday and the 9th a Friday
        $this->postJson('/leaves', $this->payload($users, '2026-10-06', '2026-10-10'))
            ->assertCreated()
            ->assertJsonPath('data.days', 3)
            ->assertJsonPath('data.dates', ['2026-10-06', '2026-10-08', '2026-10-10']);

        $this->assertDatabaseCount('user_leaves', 6);

        $leave = UserLeave::query()
            ->where('user_id', $users[1]->id)
            ->whereDate('leave_date', '2026-10-08')
            ->firstOrFail();

        $this->assertSame('full', $leave->portion);
        $this->assertSame('casual', $leave->type);
        $this->assertSame('Family event', $leave->note);
        $this->assertSame($this->manager->id, $leave->approved_by);
    }

    public function test_setting_a_leave_again_replaces_the_one_of_that_day(): void
    {
        $users = $this->fieldUsers(1);

        $this->postJson('/leaves', $this->payload($users, '2026-10-06', '2026-10-07'))->assertCreated();
        $this->postJson('/leaves', ['portion' => 'half', 'type' => 'sick'] + $this->payload($users, '2026-10-07', '2026-10-07'))->assertCreated();

        $this->assertDatabaseCount('user_leaves', 2);
        $this->assertSame('half', UserLeave::query()->whereDate('leave_date', '2026-10-07')->value('portion'));
        $this->assertSame('full', UserLeave::query()->whereDate('leave_date', '2026-10-06')->value('portion'));
    }

    public function test_leave_keeps_the_targets_of_its_days(): void
    {
        $users = $this->fieldUsers(1);
        DailyTarget::setForUsers([$users[0]->id], ['2026-10-06'], ['outbound_calls' => 50]);

        $this->postJson('/leaves', $this->payload($users, '2026-10-06', '2026-10-06'))->assertCreated();

        $this->assertDatabaseCount('daily_targets', 1);
    }

    public function test_range_without_a_working_day_is_rejected(): void
    {
        // the 9th is a Friday
        $this->postJson('/leaves', $this->payload($this->fieldUsers(1), '2026-10-09', '2026-10-09'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);

        $this->assertDatabaseCount('user_leaves', 0);
    }

    public function test_leave_needs_field_users_a_valid_range_and_known_values(): void
    {
        $users = $this->fieldUsers(1);

        $this->postJson('/leaves', $this->payload([$this->manager], '2026-10-06', '2026-10-06'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids.0']);

        $this->postJson('/leaves', $this->payload($users, '2026-10-08', '2026-10-06'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);

        $this->postJson('/leaves', ['portion' => 'quarter', 'type' => 'holiday'] + $this->payload($users, '2026-10-06', '2026-10-06'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['portion', 'type']);

        $this->assertDatabaseCount('user_leaves', 0);
    }

    public function test_full_day_leave_is_rejected_on_a_day_already_reported_on(): void
    {
        $users = $this->fieldUsers(1);
        $this->report($users[0], '2026-10-06');

        $this->postJson('/leaves', $this->payload($users, '2026-10-05', '2026-10-06'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);

        $this->assertDatabaseCount('user_leaves', 0);

        // a half day is still worked
        $this->postJson('/leaves', ['portion' => 'half'] + $this->payload($users, '2026-10-05', '2026-10-06'))->assertCreated();

        $leave = UserLeave::query()->whereDate('leave_date', '2026-10-06')->firstOrFail();

        $this->putJson("/leaves/{$leave->id}", ['leave_date' => '2026-10-06', 'portion' => 'full', 'type' => 'casual'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['leave_date']);
    }

    public function test_leaves_are_listed_edited_and_deleted(): void
    {
        $users = $this->fieldUsers(1);

        $this->postJson('/leaves', $this->payload($users, '2026-10-06', '2026-10-07'))->assertCreated();
        $leave = UserLeave::query()->whereDate('leave_date', '2026-10-06')->firstOrFail();

        $this->get('/leaves')->assertOk()->assertSee('name="user_ids[]"', false);

        $this->getJson('/leaves?draw=1&start=0&length=10&from=2026-10-07', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.leave_date', '2026-10-07')
            ->assertJsonPath('data.0.user.name', $users[0]->name)
            ->assertJsonPath('data.0.approved_by_user.name', $this->manager->name);

        $this->getJson("/leaves/{$leave->id}/edit")
            ->assertOk()
            ->assertJsonPath('leave_date', '2026-10-06')
            ->assertJsonPath('user.name', $users[0]->name);

        $this->putJson("/leaves/{$leave->id}", ['leave_date' => '2026-10-08', 'portion' => 'half', 'type' => 'sick', 'note' => 'Fever'])
            ->assertOk()
            ->assertJsonPath('data.leave_date', '2026-10-08')
            ->assertJsonPath('data.portion', 'half');

        // onto the day of the user's other leave, and onto a Friday
        $this->putJson("/leaves/{$leave->id}", ['leave_date' => '2026-10-07', 'portion' => 'half', 'type' => 'sick'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['leave_date']);

        $this->putJson("/leaves/{$leave->id}", ['leave_date' => '2026-10-09', 'portion' => 'half', 'type' => 'sick'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['leave_date']);

        $this->deleteJson("/leaves/{$leave->id}")->assertOk();
        $this->assertDatabaseCount('user_leaves', 1);
    }

    public function test_report_cannot_be_submitted_on_a_full_day_of_leave(): void
    {
        $user = $this->fieldUsers(1)[0];
        UserLeave::setForUsers([$user->id], ['2026-10-06'], ['portion' => 'full', 'type' => 'casual']);
        UserLeave::setForUsers([$user->id], ['2026-10-05'], ['portion' => 'half', 'type' => 'casual']);

        $this->actingAs($user);

        // the system files the report under the day it is sent on, which is the day of the full leave here
        $this->postJson('/daily-reports', $this->reportPayload('2026-10-06'))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['outbound_calls']);

        // a half day of leave is still worked
        Carbon::setTestNow('2026-10-05 10:00:00');
        $this->postJson('/daily-reports', $this->reportPayload('2026-10-05'))->assertCreated();

        $this->assertDatabaseCount('daily_reports', 1);
    }

    /**
     * @return list<User>
     */
    private function fieldUsers(int $count): array
    {
        return User::factory()->count($count)->create(['usages_sector' => 'field'])->all();
    }

    /**
     * @param  list<User>  $users
     */
    private function payload(array $users, string $from, string $to): array
    {
        return [
            'user_ids' => array_map(fn (User $user) => $user->id, $users),
            'from' => $from,
            'to' => $to,
            'portion' => 'full',
            'type' => 'casual',
            'note' => 'Family event',
        ];
    }

    private function report(User $user, string $date): DailyReport
    {
        return DailyReport::saveForUser($user, $this->reportPayload($date));
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

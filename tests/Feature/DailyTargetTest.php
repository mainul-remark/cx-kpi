<?php

namespace Tests\Feature;

use App\Models\DailyTarget;
use App\Models\Holiday;
use App\Models\Project;
use App\Models\SocialPlatform;
use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Uzzal\Acl\Middleware\AuthenticateWithAcl;
use Uzzal\Acl\Middleware\ResourceMaker;

class DailyTargetTest extends TestCase
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

    public function test_holiday_can_be_created_updated_and_deleted(): void
    {
        $this->postJson('/holidays', ['holiday_date' => '2026-12-16', 'title' => 'Victory Day'])
            ->assertCreated()
            ->assertJsonPath('data.holiday_date', '2026-12-16');

        $this->postJson('/holidays', ['holiday_date' => '2026-12-16', 'title' => 'Again'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['holiday_date']);

        $holiday = Holiday::query()->firstOrFail();

        $this->putJson("/holidays/{$holiday->id}", ['holiday_date' => '2026-12-16', 'title' => 'Bijoy Dibosh'])
            ->assertOk()
            ->assertJsonPath('data.title', 'Bijoy Dibosh');

        $this->deleteJson("/holidays/{$holiday->id}")->assertOk();
        $this->assertDatabaseCount('holidays', 0);
    }

    public function test_target_is_set_for_each_user_on_each_working_day(): void
    {
        [$platform, $project] = $this->platformAndProject();
        $users = $this->fieldUsers(2);
        Holiday::createOrUpdateHoliday(['holiday_date' => '2026-10-07', 'title' => 'Govt. holiday']);

        // Tuesday the 6th to Saturday the 10th: Wednesday is a holiday and the 9th a Friday
        $this->postJson('/daily-targets', $this->payload($users, '2026-10-06', '2026-10-10', $platform, $project))
            ->assertCreated()
            ->assertJsonPath('data.days', 3)
            ->assertJsonPath('data.dates', ['2026-10-06', '2026-10-08', '2026-10-10']);

        $this->assertDatabaseCount('daily_targets', 6);
        $this->assertDatabaseCount('daily_target_platform_replies', 6);
        $this->assertDatabaseCount('daily_target_project_calls', 6);

        $target = DailyTarget::query()
            ->where('user_id', $users[1]->id)
            ->whereDate('target_date', '2026-10-08')
            ->firstOrFail();

        $this->assertSame(50, $target->outbound_calls);
        $this->assertNull($target->inbound_calls);
        $this->assertSame($this->manager->id, $target->set_by);
        $this->assertSame(30, $target->platformReplies()->where('social_platform_id', $platform->id)->value('comments'));
        $this->assertSame(5, $target->platformReplies()->where('social_platform_id', $platform->id)->value('message_replies'));
        $this->assertNull($target->platformReplies()->where('social_platform_id', $platform->id)->value('inbound_calls'));
        $this->assertSame(20, $target->projectCalls()->where('project_id', $project->id)->value('inbound_calls'));
    }

    public function test_setting_a_target_again_replaces_the_one_of_that_day(): void
    {
        [$platform, $project] = $this->platformAndProject();
        $users = $this->fieldUsers(1);

        $this->postJson('/daily-targets', $this->payload($users, '2026-10-06', '2026-10-07', $platform, $project))->assertCreated();

        $second = $this->payload($users, '2026-10-07', '2026-10-07', $platform, $project);
        $second['outbound_calls'] = 80;
        $second['platforms'][0] = ['social_platform_id' => $platform->id, 'comments' => null, 'message_replies' => null];

        $this->postJson('/daily-targets', $second)->assertCreated();

        $this->assertDatabaseCount('daily_targets', 2);

        $changed = DailyTarget::query()->whereDate('target_date', '2026-10-07')->firstOrFail();
        $kept = DailyTarget::query()->whereDate('target_date', '2026-10-06')->firstOrFail();

        $this->assertSame(80, $changed->outbound_calls);
        $this->assertSame(0, $changed->platformReplies()->count());
        $this->assertSame(1, $changed->projectCalls()->count());
        $this->assertSame(50, $kept->outbound_calls);
        $this->assertSame(1, $kept->platformReplies()->count());
    }

    public function test_holiday_removes_the_targets_already_set_on_its_day(): void
    {
        [$platform, $project] = $this->platformAndProject();

        $this->postJson('/daily-targets', $this->payload($this->fieldUsers(2), '2026-10-06', '2026-10-08', $platform, $project))->assertCreated();
        $this->assertDatabaseCount('daily_targets', 6);

        $this->postJson('/holidays', ['holiday_date' => '2026-10-07', 'title' => 'Govt. holiday'])->assertCreated();

        $this->assertDatabaseCount('daily_targets', 4);
        $this->assertDatabaseCount('daily_target_platform_replies', 4);
        $this->assertSame(0, DailyTarget::query()->whereDate('target_date', '2026-10-07')->count());

        // moving the holiday clears its new day and leaves the old one without a target
        $holiday = Holiday::query()->firstOrFail();
        $this->putJson("/holidays/{$holiday->id}", ['holiday_date' => '2026-10-08', 'title' => 'Govt. holiday'])->assertOk();

        $this->assertDatabaseCount('daily_targets', 2);
        $this->assertSame(2, DailyTarget::query()->whereDate('target_date', '2026-10-06')->count());
    }

    public function test_range_without_a_working_day_is_rejected(): void
    {
        [$platform, $project] = $this->platformAndProject();

        // the 9th is a Friday
        $this->postJson('/daily-targets', $this->payload($this->fieldUsers(1), '2026-10-09', '2026-10-09', $platform, $project))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);

        $this->assertDatabaseCount('daily_targets', 0);
    }

    public function test_target_needs_field_users_a_valid_range_and_a_value(): void
    {
        [$platform, $project] = $this->platformAndProject();
        $users = $this->fieldUsers(1);

        $this->postJson('/daily-targets', $this->payload([$this->manager], '2026-10-06', '2026-10-06', $platform, $project))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['user_ids.0']);

        $this->postJson('/daily-targets', $this->payload($users, '2026-10-08', '2026-10-06', $platform, $project))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['to']);

        // the outbound call target is the one that is required, approximate targets alone are not enough
        $this->postJson('/daily-targets', [
            'user_ids' => [$users[0]->id],
            'from' => '2026-10-06',
            'to' => '2026-10-06',
            'inbound_calls' => 30,
            'platforms' => [['social_platform_id' => $platform->id, 'comments' => 10]],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['outbound_calls']);

        $this->assertDatabaseCount('daily_targets', 0);

        // and with it alone the target is set, every other one is optional
        $this->postJson('/daily-targets', [
            'user_ids' => [$users[0]->id],
            'from' => '2026-10-06',
            'to' => '2026-10-06',
            'outbound_calls' => 40,
        ])->assertCreated();

        $this->assertDatabaseCount('daily_targets', 1);
        $this->assertDatabaseCount('daily_target_platform_replies', 0);
    }

    public function test_form_offers_the_quick_ranges_with_a_week_starting_on_saturday(): void
    {
        $this->platformAndProject();
        $this->fieldUsers(1);

        $this->get('/daily-targets/create')
            ->assertOk()
            ->assertViewHas('presets', [
                ['label' => 'Today', 'from' => '2026-10-06', 'to' => '2026-10-06'],
                ['label' => 'Tomorrow', 'from' => '2026-10-07', 'to' => '2026-10-07'],
                ['label' => 'Next Week', 'from' => '2026-10-10', 'to' => '2026-10-16'],
                ['label' => 'Rest of the Month', 'from' => '2026-10-06', 'to' => '2026-10-31'],
                ['label' => 'Next Month', 'from' => '2026-11-01', 'to' => '2026-11-30'],
            ])
            ->assertSee('name="platforms[0][comments]"', false)
            ->assertSee('name="projects[0][message_replies]"', false);
    }

    public function test_targets_are_listed_edited_and_deleted(): void
    {
        [$platform, $project] = $this->platformAndProject();
        $users = $this->fieldUsers(1);

        $this->postJson('/daily-targets', $this->payload($users, '2026-10-06', '2026-10-06', $platform, $project))->assertCreated();
        $target = DailyTarget::query()->firstOrFail();

        $this->getJson('/daily-targets?draw=1&start=0&length=10', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('recordsTotal', 1)
            ->assertJsonPath('data.0.target_date', '2026-10-06')
            ->assertJsonPath('data.0.platform_comments', 30)
            ->assertJsonPath('data.0.project_comments', null)
            ->assertJsonPath('data.0.set_by_user.name', $this->manager->name);

        $this->get("/daily-targets/{$target->id}/edit")
            ->assertOk()
            ->assertViewHas('selectedUserIds', [$users[0]->id])
            ->assertViewHas('from', '2026-10-06');

        // a platform holding target rows cannot be removed
        $this->deleteJson("/social-platforms/{$platform->id}")->assertStatus(409);

        $this->deleteJson("/daily-targets/{$target->id}")->assertOk();
        $this->assertDatabaseCount('daily_targets', 0);
        $this->assertDatabaseCount('daily_target_platform_replies', 0);
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
    private function payload(array $users, string $from, string $to, SocialPlatform $platform, Project $project): array
    {
        return [
            'user_ids' => array_map(fn (User $user) => $user->id, $users),
            'from' => $from,
            'to' => $to,
            'outbound_calls' => 50,
            'inbound_calls' => null,
            'platforms' => [
                ['social_platform_id' => $platform->id, 'comments' => 30, 'message_replies' => 5],
            ],
            'projects' => [
                ['project_id' => $project->id, 'inbound_calls' => 20],
            ],
        ];
    }
}

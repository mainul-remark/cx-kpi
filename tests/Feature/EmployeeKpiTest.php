<?php

namespace Tests\Feature;

use App\Exports\EmployeeKpiExport;
use App\Exports\EmployeeKpiUserSheet;
use App\Jobs\GenerateKpiExport;
use App\Models\DailyReport;
use App\Models\DailyTarget;
use App\Models\KpiSnapshot;
use App\Models\PeriodTarget;
use App\Models\Project;
use App\Models\SocialPlatform;
use App\Models\User;
use App\Models\UserLeave;
use App\Services\Kpi\EmployeeKpiService;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use Uzzal\Acl\Middleware\AuthenticateWithAcl;
use Uzzal\Acl\Middleware\ResourceMaker;

class EmployeeKpiTest extends TestCase
{
    use RefreshDatabase;

    private User $manager;
    private User $agent;
    private User $otherAgent;
    private SocialPlatform $facebook;
    private SocialPlatform $instagram;
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
        $this->agent = User::factory()->create(['usages_sector' => 'field', 'name' => 'Agent One', 'employee_id' => 'M289', 'created_at' => '2026-09-01 09:00:00']);
        $this->otherAgent = User::factory()->create(['usages_sector' => 'field', 'name' => 'Agent Two', 'employee_id' => null, 'created_at' => '2026-09-01 09:00:00']);
        $this->facebook = SocialPlatform::createOrUpdateSocialPlatform(['name' => 'Facebook']);
        $this->instagram = SocialPlatform::createOrUpdateSocialPlatform(['name' => 'Instagram']);
        $this->project = Project::createOrUpdateProject(['name' => 'Inbound Support']);
    }

    public function test_kpi_matches_reports_to_targets_day_by_day(): void
    {
        $this->seedAgentOne();

        $row = $this->kpi()->detail($this->agent, '2026-09-30', '2026-10-08');

        // only the outbound calls are scored: 30 of 50, nothing of 50, a half day at 25 of 25 and 80 of 50
        $this->assertSame(175, $row['target_total']);
        $this->assertSame(135, $row['actual_total']);
        $this->assertEquals(77.1, $row['pct']);
        // outbound calls carry 70 of the 100 and the Facebook comments the other 30: 70 x 135/175 + 30 x 26/35
        $this->assertEquals(76.3, $row['score']);
        $this->assertSame(['outbound_calls', 'platform:'.$this->facebook->id.':comments'], array_keys($row['lines']));
        $this->assertEquals([0.7, 0.3], array_column($row['lines'], 'weight'));
        $this->assertEquals([54.0, 22.29], array_map(fn ($score) => round($score, 2), array_column($row['lines'], 'score')));
        $this->assertSame(4, $row['target_days']);
        $this->assertSame(4, $row['worked']);
        $this->assertSame(1, $row['absent']);
        $this->assertEquals(1.5, $row['leave']);

        $breakdown = collect($row['breakdown'])->keyBy('key');
        $this->assertSame(175, $breakdown['outbound_calls']['target']);
        $this->assertSame(135, $breakdown['outbound_calls']['actual']);
        // the comment target is listed with its figures, though it is not part of the outbound totals
        $this->assertSame(35, $breakdown['comments']['target']);
        // the Instagram comments had no target, so they are not counted
        $this->assertSame(26, $breakdown['comments']['actual']);
        $this->assertNull($breakdown['inbound_calls']['target']);
        $this->assertSame(0, $breakdown['inbound_calls']['actual']);
        $this->assertNull($breakdown['message_replies']['pct']);

        $days = collect($row['days'])->keyBy('date');

        // tomorrow and the day after are not held against anyone yet
        $this->assertSame(['2026-09-30', '2026-10-01', '2026-10-02', '2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06'], $days->keys()->all());
        $this->assertSame('worked', $days['2026-09-30']['status']);
        $this->assertNull($days['2026-09-30']['target']);
        $this->assertSame(30, $days['2026-10-01']['actual']);
        $this->assertSame('off', $days['2026-10-02']['status']);
        $this->assertSame('absent', $days['2026-10-03']['status']);
        $this->assertSame(0, $days['2026-10-03']['actual']);
        $this->assertSame('leave', $days['2026-10-04']['status']);
        $this->assertNull($days['2026-10-04']['target']);
        $this->assertTrue($days['2026-10-05']['half_leave']);
        $this->assertSame(25, $days['2026-10-05']['target']);
        $this->assertEquals(160, $days['2026-10-06']['pct']);
    }

    public function test_score_is_capped_and_empty_without_a_target(): void
    {
        $this->seedAgentOne();

        $row = $this->kpi()->detail($this->agent, '2026-10-06', '2026-10-06');

        $this->assertEquals(160, $row['pct']);
        $this->assertEquals(100, $row['score']);

        $none = $this->kpi()->detail($this->otherAgent, '2026-10-01', '2026-10-06');

        $this->assertSame(0, $none['target_total']);
        $this->assertNull($none['pct']);
        $this->assertNull($none['score']);
        $this->assertSame(5, $none['absent']);

        // a range that has not started yet
        $this->assertSame([], $this->kpi()->detail($this->agent, '2026-10-07', '2026-10-08')['days']);
    }

    public function test_days_before_a_user_was_added_are_not_held_against_them(): void
    {
        $newcomer = User::factory()->create(['usages_sector' => 'field', 'name' => 'Agent Three', 'created_at' => '2026-10-05 09:00:00']);
        DailyTarget::setForUsers([$newcomer->id], ['2026-10-03', '2026-10-05'], ['outbound_calls' => 60]);

        $row = $this->kpi()->detail($newcomer, '2026-10-03', '2026-10-06');

        $this->assertSame(60, $row['target_total']);
        $this->assertSame(2, $row['absent']);
        $this->assertEquals(0, $row['pct']);
        $this->assertSame('not_joined', $row['days'][0]['status']);
    }

    public function test_corporate_user_sees_the_sheet_with_the_best_score_first(): void
    {
        $this->seedAgentOne();
        DailyTarget::setForUsers([$this->otherAgent->id], ['2026-10-06'], ['outbound_calls' => 10]);
        $this->report($this->otherAgent, '2026-10-06', 10);

        $this->actingAs($this->manager)->get('/kpi')->assertOk()->assertSee('id="kpi_user"', false)->assertSee('Agent Two');

        $this->data($this->manager, ['preset' => 'custom', 'from' => '2026-09-30', 'to' => '2026-10-08'])
            ->assertOk()
            ->assertJsonPath('show_targets', true)
            ->assertJsonPath('range.to', '2026-10-08')
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.0.name', 'Agent Two')
            ->assertJsonPath('rows.0.score', 100)
            ->assertJsonPath('rows.1.name', 'Agent One')
            ->assertJsonPath('rows.1.target_total', 175)
            ->assertJsonPath('summary.users', 2)
            ->assertJsonPath('summary.target_total', 185)
            ->assertJsonPath('summary.actual_total', 145)
            ->assertJsonPath('summary.pct', 78.4);

        $this->data($this->manager, ['preset' => 'today', 'user_id' => $this->agent->id])
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.name', 'Agent One');

        $this->actingAs($this->manager)
            ->getJson("/kpi/{$this->agent->id}?preset=custom&from=2026-10-01&to=2026-10-06", ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('user.name', 'Agent One')
            ->assertJsonCount(6, 'user.days')
            ->assertJsonPath('user.days.0.target', 50);
    }

    public function test_field_user_sees_only_their_own_score_without_targets(): void
    {
        $this->seedAgentOne();

        $this->actingAs($this->agent)->get('/kpi')->assertOk()->assertDontSee('id="kpi_user"', false)->assertDontSee('Agent Two');

        $this->data($this->agent, ['preset' => 'custom', 'from' => '2026-09-30', 'to' => '2026-10-08', 'user_id' => $this->otherAgent->id])
            ->assertOk()
            ->assertJsonPath('show_targets', false)
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.name', 'Agent One')
            ->assertJsonPath('rows.0.target_total', null)
            ->assertJsonPath('rows.0.breakdown.0.target', null)
            ->assertJsonPath('rows.0.actual_total', 135)
            ->assertJsonPath('summary.target_total', null);

        $this->actingAs($this->agent)
            ->getJson("/kpi/{$this->agent->id}?preset=custom&from=2026-10-01&to=2026-10-06", ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('user.days.0.target', null)
            ->assertJsonPath('user.days.0.actual', 30);

        $this->actingAs($this->agent)
            ->getJson("/kpi/{$this->otherAgent->id}?preset=today", ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertForbidden();
    }

    public function test_activity_weights_change_the_kpi_but_not_the_counts(): void
    {
        $this->seedAgentOne();

        $row = $this->kpi()->detail($this->agent, '2026-09-30', '2026-10-08');

        // outbound calls alone are scored, the approximate comment target is not: 135 of 175
        $this->assertEquals(77.1, $row['pct']);
        $this->assertSame(175, $row['target_total']);
        $this->assertSame(135, $row['actual_total']);

        config(['kpi.weights.comments' => 3]);

        $row = $this->kpi()->detail($this->agent, '2026-09-30', '2026-10-08');

        // 135 + 3 x 26 of 175 + 3 x 35
        $this->assertEquals(76.1, $row['pct']);
        $this->assertSame(210, $row['target_total']);
        $this->assertSame(161, $row['actual_total']);
    }

    public function test_sheet_can_be_downloaded_as_excel(): void
    {
        $this->seedAgentOne();
        Excel::fake();

        $token = $this->actingAs($this->manager)->get('/kpi/export?preset=custom&from=2026-09-30&to=2026-10-08')
            ->assertStatus(202)
            ->assertJsonStructure(['token'])
            ->json('token');

        // the queue is synchronous in the tests, so the job has already stored the workbook
        Excel::assertStored(GenerateKpiExport::path($token), 'local', function (EmployeeKpiExport $export) {
            $sheets = collect($export->sheets())->keyBy(fn (EmployeeKpiUserSheet $sheet) => $sheet->title());
            $one = $sheets['M289']->array();
            $two = $sheets['Agent Two']->array();

            // a tab per user under their employee id, or their name without one
            $this->assertSame(['Agent Two', 'M289'], $sheets->keys()->all());
            $this->assertSame(EmployeeKpiUserSheet::HEADINGS, $one[0]);

            // outbound calls at 135 of 175 carry 70%, the Facebook comments at 26 of 35 the other 30%, as formulas
            $this->assertSame(["Agent One\n(M289)", 'Doer', 'Outbound Calls', 175, 135, '=IF(D2=0,0,MIN(E2/D2,1))', 0.7, '=F2*G2*100', 'Monthly'], $one[1]);
            $this->assertSame([null, null, 'Facebook (Comments)', 35, 26, '=IF(D3=0,0,MIN(E3/D3,1))', 0.3, '=F3*G3*100', 'Monthly'], $one[2]);
            $this->assertSame([null, null, 'Total Score', null, null, null, '=SUM(G2:G3)', '=ROUND(SUM(H2:H3),2)', null], $one[3]);
            $this->assertCount(4, $one);

            $this->assertSame(['Agent Two', 'Doer', 'No target set in this period', null, null, null, null, null, 'Monthly'], $two[1]);
            $this->assertCount(2, $two);

            return true;
        });

        // the workbook shows the targets, which a field user is not shown
        $this->actingAs($this->agent)->get('/kpi/export?preset=custom&from=2026-09-30&to=2026-10-08')->assertForbidden();
    }

    public function test_export_is_queued_and_downloaded_once_when_ready(): void
    {
        $this->seedAgentOne();

        $token = $this->actingAs($this->manager)->get('/kpi/export?preset=custom&from=2026-09-30&to=2026-10-08')->assertStatus(202)->json('token');
        $path = GenerateKpiExport::path($token);

        Storage::disk('local')->assertExists($path);
        $this->get('/kpi/export?token='.$token)->assertOk()->assertExactJson(['status' => 'done']);

        // somebody else cannot see it, ask for it or take it
        $this->actingAs(User::factory()->create(['usages_sector' => 'corporate']))->get('/kpi/export?token='.$token)->assertNotFound();
        $this->get('/kpi/export?token='.$token.'&download=1')->assertNotFound();

        $response = $this->actingAs($this->manager)->get('/kpi/export?token='.$token.'&download=1')
            ->assertOk()
            ->assertDownload('kpi-sheet_2026-09-30_to_2026-10-08.xlsx');

        // the file goes as soon as it has been sent (which a test response never does), and the token is spent
        $deleteAfterSend = new \ReflectionProperty(BinaryFileResponse::class, 'deleteFileAfterSend');
        $this->assertTrue($deleteAfterSend->getValue($response->baseResponse));
        Storage::disk('local')->delete($path);
        $this->get('/kpi/export?token='.$token.'&download=1')->assertNotFound();
        $this->get('/kpi/export?token='.$token)->assertNotFound();

        // a range that is too long is refused before anything is queued
        $this->get('/kpi/export?preset=custom&from=2020-01-01&to=2026-10-08')->assertStatus(302);
    }

    public function test_failed_export_is_reported_to_the_page(): void
    {
        $this->seedAgentOne();
        Cache::put(GenerateKpiExport::cacheKey('abc'), ['status' => 'pending', 'user_id' => $this->manager->id], 60);

        (new GenerateKpiExport('abc', $this->manager->id, '2026-10-01', '2026-10-06', null, 'month'))->failed(new \RuntimeException('boom'));

        $this->actingAs($this->manager)->get('/kpi/export?token=abc')->assertOk()->assertJsonPath('status', 'failed');
    }

    public function test_purge_command_removes_exports_nobody_downloaded(): void
    {
        $disk = Storage::disk('local');
        $disk->put(GenerateKpiExport::path('old'), 'x');
        $disk->put(GenerateKpiExport::path('new'), 'x');
        touch($disk->path(GenerateKpiExport::path('old')), now()->subHour()->getTimestamp());

        $this->artisan('kpi:purge-exports')->assertSuccessful();

        $disk->assertMissing(GenerateKpiExport::path('old'));
        $disk->assertExists(GenerateKpiExport::path('new'));

        $disk->delete(GenerateKpiExport::path('new'));
    }

    public function test_exported_workbook_opens_with_a_tab_per_user_and_capped_lines(): void
    {
        $this->seedAgentOne();
        // two users without an employee id under the same name, and an id a tab name cannot hold
        $namesake = User::factory()->create(['usages_sector' => 'field', 'name' => 'Agent Two', 'employee_id' => null, 'created_at' => '2026-09-01 09:00:00']);
        $messages = fn (int $target) => [['social_platform_id' => $this->facebook->id, 'message_replies' => $target]];
        DailyTarget::setForUsers([$namesake->id], ['2026-10-06'], ['outbound_calls' => 1000, 'inbound_calls' => 2, 'platforms' => $messages(2)]);
        $third = User::factory()->create(['usages_sector' => 'field', 'name' => 'Agent Three', 'employee_id' => 'IT/[7]:9', 'created_at' => '2026-09-01 09:00:00']);
        DailyTarget::setForUsers([$third->id], ['2026-10-06'], ['outbound_calls' => 10, 'inbound_calls' => 0, 'platforms' => $messages(30)]);
        $this->report($third, '2026-10-06', 25);

        $token = $this->actingAs($this->manager)->get('/kpi/export?preset=today')->assertStatus(202)->json('token');
        $response = $this->get('/kpi/export?token='.$token.'&download=1')->assertOk();

        $book = IOFactory::load($response->baseResponse->getFile()->getPathname());
        @unlink($response->baseResponse->getFile()->getPathname());

        $this->assertSame(['Agent Two', 'Agent Two (2)', 'IT  7  9', 'M289'], $book->getSheetNames());

        $sheet = $book->getSheet(2);

        // 25 calls of 10 count as the full 70% and no more, the 8 replies of 30 for their part of the other 30%
        $this->assertSame('Outbound Calls', $sheet->getCell('C2')->getValue());
        $this->assertEquals(1, $sheet->getCell('F2')->getCalculatedValue());
        $this->assertEquals(0.7, $sheet->getCell('G2')->getValue());
        $this->assertEquals(70, $sheet->getCell('H2')->getCalculatedValue());
        $this->assertSame('Facebook (Message Replies)', $sheet->getCell('C3')->getValue());
        $this->assertEquals(30, $sheet->getCell('D3')->getValue());
        $this->assertEquals(8, $sheet->getCell('E3')->getValue());
        $this->assertEquals(0.3, $sheet->getCell('G3')->getValue());
        // the target of 0 for inbound calls is nothing to measure against, so it has no line
        $this->assertSame('Total Score', $sheet->getCell('C4')->getValue());
        $this->assertEquals(1, $sheet->getCell('G4')->getCalculatedValue());
        $this->assertEquals(78, $sheet->getCell('H4')->getCalculatedValue());
        $this->assertSame('Daily', $sheet->getCell('I2')->getValue());
        $this->assertSame(['A2:A3', 'B2:B3'], array_keys($sheet->getMergeCells()));
        $this->assertSame('0%', $sheet->getStyle('G2')->getNumberFormat()->getFormatCode());

        // an admin changing a weight on the sheet moves the total with it
        $sheet->getCell('G2')->setValue(0.5);
        $sheet->getCell('G3')->setValue(0.5);
        \PhpOffice\PhpSpreadsheet\Calculation\Calculation::getInstance($book)->clearCalculationCache();
        // 50 + 50 x 8/30
        $this->assertEquals(63.33, round($sheet->getCell('H4')->getCalculatedValue(), 2));

        // the 30 left over is split equally and in whole percents among the others
        $shared = collect($book->getAllSheets())->first(fn ($tab) => $tab->getCell('D2')->getValue() == 1000);
        $this->assertSame('Outbound Calls', $shared->getCell('C2')->getValue());
        $this->assertEquals([0.7, 0.15, 0.15], [$shared->getCell('G2')->getValue(), $shared->getCell('G3')->getValue(), $shared->getCell('G4')->getValue()]);
        $this->assertEquals(1, $shared->getCell('G5')->getCalculatedValue());

        $this->assertSame('Monthly', EmployeeKpiExport::frequency('2026-10-01', '2026-10-31'));
        $this->assertSame('Yearly', EmployeeKpiExport::frequency('2026-01-01', '2026-12-31'));

        // this month is monthly even with only its first six days gone, a typed range goes by its length
        Excel::fake();
        $token = $this->actingAs($this->manager)->get('/kpi/export?preset=month')->assertStatus(202)->json('token');
        Excel::assertStored(GenerateKpiExport::path($token), 'local', fn (EmployeeKpiExport $export) => $export->sheets()[0]->array()[1][8] === 'Monthly');

        $token = $this->actingAs($this->manager)->get('/kpi/export')->assertStatus(202)->json('token');
        Excel::assertStored(GenerateKpiExport::path($token), 'local', fn (EmployeeKpiExport $export) => $export->sheets()[0]->array()[1][8] === 'Monthly');

        $this->assertSame('Weekly', EmployeeKpiExport::frequency('2026-10-01', '2026-10-06', 'custom'));
        $this->assertSame('Weekly', EmployeeKpiExport::frequency('2026-10-03', '2026-10-06', 'week'));
        $this->assertSame('Monthly', EmployeeKpiExport::frequency('2026-09-22', '2026-10-06', '15days'));
    }

    public function test_a_strong_day_makes_up_for_a_weak_one_within_the_range(): void
    {
        // 100 a day: 80 on the first day and 120 on the second is 200 of 200, the full score
        DailyTarget::setForUsers([$this->agent->id], ['2026-10-03', '2026-10-05'], ['outbound_calls' => 100]);
        $this->report($this->agent, '2026-10-03', 80);
        $this->report($this->agent, '2026-10-05', 120);

        $row = $this->kpi()->detail($this->agent, '2026-10-01', '2026-10-06');

        $this->assertSame(200, $row['target_total']);
        $this->assertSame(200, $row['actual_total']);
        $this->assertEquals(100, $row['pct']);
        $this->assertEquals(100, $row['score']);
        $this->assertEquals(1, $row['lines']['outbound_calls']['weight']);

        // 80 and 70 of 200 is short, and the score says so
        $this->report($this->agent, '2026-10-05', 70);
        $this->assertEquals(75, $this->kpi()->detail($this->agent, '2026-10-01', '2026-10-06')['score']);
    }

    public function test_outbound_calls_carry_their_share_and_the_rest_is_split_equally(): void
    {
        $line = fn (int $target, int $actual, string $metric = 'comments') => ['metric' => $metric, 'target' => $target, 'actual' => $actual];

        // outbound calls alone carry everything
        $only = EmployeeKpiService::lines(['outbound_calls' => $line(10, 5, 'outbound_calls')], 70);
        $this->assertEquals(1, $only['outbound_calls']['weight']);
        $this->assertEquals(50, $only['outbound_calls']['score']);

        // two others share the 30, three share it in tens
        $two = EmployeeKpiService::lines(['outbound_calls' => $line(10, 10, 'outbound_calls'), 'a' => $line(10, 10), 'b' => $line(10, 0)], 70);
        $this->assertEquals([0.7, 0.15, 0.15], array_column($two, 'weight'));
        $this->assertEquals(85, array_sum(array_column($two, 'score')));

        $three = EmployeeKpiService::lines(['outbound_calls' => $line(10, 10, 'outbound_calls'), 'a' => $line(1, 1), 'b' => $line(1, 1), 'c' => $line(1, 1)], 70);
        $this->assertEquals([0.7, 0.1, 0.1, 0.1], array_column($three, 'weight'));

        // 300% on the others cannot cover the outbound calls: 70 x 0.5 + 30 x 1
        $short = EmployeeKpiService::lines(['outbound_calls' => $line(10, 5, 'outbound_calls'), 'a' => $line(10, 30)], 70);
        $this->assertEquals(65, array_sum(array_column($short, 'score')));

        // a share that does not divide leaves the odd percents to the first ones, and the weights still add up to 100
        $odd = EmployeeKpiService::lines(['outbound_calls' => $line(10, 10, 'outbound_calls'), 'a' => $line(1, 1), 'b' => $line(1, 1), 'c' => $line(1, 1), 'd' => $line(1, 1), 'e' => $line(1, 1), 'f' => $line(1, 1), 'g' => $line(1, 1)], 70);
        $this->assertEquals(1, round(array_sum(array_column($odd, 'weight')), 6));

        // no outbound target: the others share the whole, and a target of 0 is no line
        $none = EmployeeKpiService::lines(['outbound_calls' => $line(0, 4, 'outbound_calls'), 'a' => $line(10, 10), 'b' => $line(10, 5)], 70);
        $this->assertSame(['a', 'b'], array_keys($none));
        $this->assertEquals([0.5, 0.5], array_column($none, 'weight'));
        $this->assertSame([], EmployeeKpiService::lines([], 70));

        // the share comes from config
        config(['kpi.outbound_share' => 50]);
        $this->seedAgentOne();
        $row = $this->kpi()->detail($this->agent, '2026-09-30', '2026-10-08');
        $this->assertEquals([0.5, 0.5], array_column($row['lines'], 'weight'));
    }

    public function test_targets_can_be_set_for_a_month_instead_of_each_day(): void
    {
        config(['kpi.target_mode' => 'period']);
        $this->seedAgentOne();

        // October has 26 working days. Agent One was asked for 3.5 of them by the 6th: the 1st, 3rd, 6th,
        // half of the 5th, nothing on the day off or the day of leave
        PeriodTarget::setForUser($this->agent->id, '2026-10-01', [
            'outbound_calls' => 1040,
            'platform:'.$this->facebook->id.':comments' => 260,
            'inbound_calls' => 0,
        ]);

        $row = $this->kpi()->detail($this->agent, '2026-10-01', '2026-10-31');
        $comments = 'platform:'.$this->facebook->id.':comments';

        $this->assertSame(140, $row['target_total']);
        $this->assertSame(135, $row['actual_total']);
        $this->assertEquals(96.4, $row['pct']);
        $this->assertEquals(89.8, $row['score']);
        $this->assertSame(['outbound_calls', $comments], array_keys($row['lines']));
        $this->assertSame(35, $row['lines'][$comments]['target']);
        $this->assertSame(26, $row['lines'][$comments]['actual']);
        // no target is shown for a day, what was reported on it is
        $this->assertNull($row['days'][0]['target']);
        $this->assertSame(30, $row['days'][0]['actual']);

        // a user without a monthly target has no score, whatever daily targets were set for them
        $this->assertNull($this->kpi()->detail($this->otherAgent, '2026-10-01', '2026-10-31')['score']);

        // a month still to come asks nothing of anyone
        PeriodTarget::setForUser($this->agent->id, '2026-11-01', ['outbound_calls' => 500]);
        $this->assertSame(140, $this->kpi()->detail($this->agent, '2026-10-01', '2026-11-30')['target_total']);

        // back to the daily targets
        config(['kpi.target_mode' => 'daily']);
        $this->assertSame(175, $this->kpi()->detail($this->agent, '2026-09-30', '2026-10-08')['target_total']);
    }

    public function test_month_is_frozen_once_it_is_over(): void
    {
        $this->seedAgentOne();

        // October is still running
        $this->artisan('kpi:snapshot', ['month' => '2026-10'])->assertFailed();
        $this->artisan('kpi:snapshot', ['month' => 'October'])->assertFailed();
        $this->assertDatabaseCount('kpi_snapshots', 0);

        Carbon::setTestNow('2026-11-02 10:00:00');

        $this->artisan('kpi:snapshot')->assertSuccessful();

        $this->assertDatabaseCount('kpi_snapshots', 2);
        $snapshot = KpiSnapshot::query()->where('user_id', $this->agent->id)->firstOrFail();

        // the two days that were still to come ended without a report: 135 of 275
        $this->assertSame('2026-10-01', $snapshot->period_month->toDateString());
        $this->assertSame(275, $snapshot->target_total);
        $this->assertSame(135, $snapshot->actual_total);
        $this->assertEquals(49.1, $snapshot->pct);
        $this->assertEquals(1.5, $snapshot->leave);

        // a report changed after the month was frozen leaves the score as it was
        $this->report($this->agent, '2026-10-07', 60, 10);
        $this->artisan('kpi:snapshot', ['month' => '2026-10'])->assertSuccessful();
        $this->assertEquals(49.1, $snapshot->fresh()->pct);

        $this->actingAs($this->manager)
            ->getJson('/kpi/monthly?month=2026-10', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonPath('month', '2026-10')
            ->assertJsonCount(2, 'rows')
            ->assertJsonPath('rows.0.name', 'Agent One')
            ->assertJsonPath('rows.0.target_total', 275)
            ->assertJsonPath('rows.0.generated_at', '2026-11-02');

        $this->actingAs($this->otherAgent)
            ->getJson('/kpi/monthly', ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->assertJsonCount(1, 'rows')
            ->assertJsonPath('rows.0.name', 'Agent Two')
            ->assertJsonPath('rows.0.target_total', null)
            ->assertJsonPath('rows.0.score', null);

        // unless it is asked to be worked out again
        $this->artisan('kpi:snapshot', ['month' => '2026-10', '--force' => true])->assertSuccessful();
        $this->assertSame(195, $snapshot->fresh()->actual_total);
        $this->assertDatabaseCount('kpi_snapshots', 2);
    }

    public function test_invalid_filters_are_rejected(): void
    {
        $this->data($this->manager, ['preset' => 'custom'])->assertStatus(422)->assertJsonValidationErrors(['from', 'to']);
        $this->data($this->manager, ['preset' => 'custom', 'from' => '2024-01-01', 'to' => '2026-10-01'])
            ->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->data($this->manager, ['preset' => 'year'])->assertStatus(422)->assertJsonValidationErrors(['preset']);
    }

    /**
     * Agent One from Wednesday the 30th: a day without a target, a day short of it, an off day,
     * a day without a report, a full and a half day of leave, a day above the target and two days still to come.
     */
    private function seedAgentOne(): void
    {
        DailyTarget::setForUsers(
            [$this->agent->id],
            ['2026-10-01', '2026-10-03', '2026-10-04', '2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08'],
            ['outbound_calls' => 50, 'platforms' => [['social_platform_id' => $this->facebook->id, 'comments' => 10]]]
        );

        UserLeave::setForUsers([$this->agent->id], ['2026-10-04'], ['portion' => 'full', 'type' => 'casual']);
        UserLeave::setForUsers([$this->agent->id], ['2026-10-05'], ['portion' => 'half', 'type' => 'casual']);

        $this->report($this->agent, '2026-09-30', 20, 5);
        $this->report($this->agent, '2026-10-01', 30, 12, 20);
        $this->report($this->agent, '2026-10-05', 25, 4);
        $this->report($this->agent, '2026-10-06', 80, 10);
    }

    private function kpi(): EmployeeKpiService
    {
        return app(EmployeeKpiService::class);
    }

    private function data(User $user, array $filters): TestResponse
    {
        return $this->actingAs($user)->getJson('/kpi?'.http_build_query($filters), ['X-Requested-With' => 'XMLHttpRequest']);
    }

    private function report(User $user, string $date, int $outbound, int $facebook = 0, int $instagram = 0): DailyReport
    {
        return DailyReport::saveForUser($user, [
            'report_date' => $date,
            'outbound_calls' => $outbound,
            'inbound_calls' => 5,
            'platforms' => [
                ['social_platform_id' => $this->facebook->id, 'comments' => $facebook, 'message_replies' => 8],
                ['social_platform_id' => $this->instagram->id, 'comments' => $instagram],
            ],
            'projects' => [['project_id' => $this->project->id, 'inbound_calls' => 7]],
        ]);
    }
}

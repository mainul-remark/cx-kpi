<?php

namespace Tests\Feature;

use App\Exports\HolidaySampleExport;
use App\Models\Holiday;
use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;
use Uzzal\Acl\Middleware\AuthenticateWithAcl;
use Uzzal\Acl\Middleware\ResourceMaker;

class HolidayImportTest extends TestCase
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

        Carbon::setTestNow('2026-10-06 10:00:00');

        $this->manager = User::factory()->create(['usages_sector' => 'corporate']);
    }

    public function test_holidays_are_imported_from_a_csv_file(): void
    {
        $this->import($this->csv("Date,Title\n2026-12-16,Victory Day\n25/12/2026,Christmas Day\n\n1/5/2027,May Day\n"))
            ->assertOk()
            ->assertJsonPath('created_count', 3)
            ->assertJsonPath('updated_count', 0);

        $this->assertSame(
            ['2026-12-16' => 'Victory Day', '2026-12-25' => 'Christmas Day', '2027-05-01' => 'May Day'],
            $this->holidays()
        );
    }

    public function test_sample_files_can_be_downloaded_and_imported_as_they_are(): void
    {
        $this->actingAs($this->manager)->get('/holidays/sample')
            ->assertOk()
            ->assertDownload('import-holidays.xlsx');
        $this->actingAs($this->manager)->get('/holidays/sample?format=csv')
            ->assertOk()
            ->assertDownload('import-holidays.csv');

        $xlsx = UploadedFile::fake()->createWithContent('import-holidays.xlsx', Excel::raw(new HolidaySampleExport(), ExcelFormat::XLSX));
        $this->import($xlsx)->assertOk()->assertJsonPath('created_count', 3);

        // the same days again, from the csv this time
        $csv = UploadedFile::fake()->createWithContent('import-holidays.csv', Excel::raw(new HolidaySampleExport(), ExcelFormat::CSV));
        $this->import($csv)->assertOk()->assertJsonPath('created_count', 0)->assertJsonPath('updated_count', 3);

        $this->assertSame('Victory Day', $this->holidays()['2026-12-16']);
        $this->assertCount(3, $this->holidays());
    }

    public function test_a_date_that_is_already_a_holiday_gets_the_new_title(): void
    {
        Holiday::createOrUpdateHoliday(['holiday_date' => '2026-12-16', 'title' => 'Old title']);

        $this->import($this->csv("Date,Title\n2026-12-16,Victory Day\n"))
            ->assertOk()
            ->assertJsonPath('created_count', 0)
            ->assertJsonPath('updated_count', 1);

        $this->assertSame(['2026-12-16' => 'Victory Day'], $this->holidays());
    }

    public function test_nothing_is_imported_when_a_row_is_invalid(): void
    {
        $response = $this->import($this->csv("Date,Title\n2026-12-16,Victory Day\n31/02/2026,No such day\n2026-12-16,Again\n2026-12-25,\nsoon,\n"))
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $errors = collect($response->json('errors'))->pluck('errors', 'row');

        $this->assertSame([3, 4, 5, 6], $errors->keys()->all());
        $this->assertStringContainsString('valid date', $errors[3][0]);
        $this->assertStringContainsString('duplicated', $errors[4][0]);
        $this->assertSame(['Title is required.'], $errors[5]);
        $this->assertCount(2, $errors[6]);
        $this->assertSame([], $this->holidays());
    }

    public function test_the_file_must_be_a_spreadsheet_with_holidays_in_it(): void
    {
        $this->actingAs($this->manager)->postJson('/holidays/import', [])
            ->assertStatus(422)->assertJsonValidationErrors(['file']);

        $this->import(UploadedFile::fake()->create('holidays.pdf', 10, 'application/pdf'))
            ->assertStatus(422)->assertJsonValidationErrors(['file']);

        $this->import($this->csv("Day,Name\n2026-12-16,Victory Day\n"))->assertStatus(422);
        $this->import($this->csv("Date,Title\n"))->assertStatus(422)->assertJsonPath('success', false);

        $this->assertSame([], $this->holidays());
    }

    private function import(UploadedFile $file): TestResponse
    {
        return $this->actingAs($this->manager)->postJson('/holidays/import', ['file' => $file]);
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('holidays.csv', $content);
    }

    /**
     * @return array<string, string> titles by date
     */
    private function holidays(): array
    {
        return Holiday::query()->orderBy('holiday_date')->get()
            ->mapWithKeys(fn (Holiday $holiday) => [$holiday->holiday_date->toDateString() => $holiday->title])
            ->all();
    }
}

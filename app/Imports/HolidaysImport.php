<?php

namespace App\Imports;

use App\Models\Holiday;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\SkipsEmptyRows;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * Reads holidays from a sheet with a "Date" and a "Title" column.
 *
 * Nothing is saved unless every row is valid, so a file can be fixed and uploaded again
 * without leaving half of it behind. A date that is already a holiday gets the new title.
 */
class HolidaysImport implements ToCollection, WithHeadingRow, SkipsEmptyRows
{
    /**
     * The ways a date may be written as text. A date cell of a spreadsheet is read whatever its format.
     */
    private const DATE_FORMATS = ['Y-m-d', 'd/m/Y', 'j/n/Y', 'd-m-Y', 'j-n-Y', 'd M Y', 'j M Y', 'd F Y', 'j F Y'];

    private array $failures = [];
    private int $createdCount = 0;
    private int $updatedCount = 0;

    public function collection(Collection $rows): void
    {
        $excelRow = 1;
        $validRows = [];

        foreach ($rows as $row) {
            $excelRow++;

            $rowData = $row->toArray();
            $date = $this->parseDate($rowData['date'] ?? null);
            $title = trim((string) ($rowData['title'] ?? ''));
            $rowErrors = [];

            if (trim((string) ($rowData['date'] ?? '')) === '') {
                $rowErrors[] = 'Date is required.';
            } elseif ($date === null) {
                $rowErrors[] = 'Date must be a valid date, written like '.today()->toDateString().'.';
            } elseif (isset($validRows[$date])) {
                $rowErrors[] = "Date \"{$date}\" is duplicated in the file.";
            }

            if ($title === '') {
                $rowErrors[] = 'Title is required.';
            } elseif (mb_strlen($title) > 255) {
                $rowErrors[] = 'Title must not be longer than 255 characters.';
            }

            if ($rowErrors !== []) {
                $this->failures[] = [
                    'row'    => $excelRow,
                    'errors' => $rowErrors,
                ];
                continue;
            }

            $validRows[$date] = $title;
        }

        if ($this->failures !== [] || $validRows === []) {
            return;
        }

        DB::transaction(function () use ($validRows) {
            // a plain range, as the date may be stored with a time part
            $existing = Holiday::query()
                ->whereBetween('holiday_date', [min(array_keys($validRows)), max(array_keys($validRows)).' 23:59:59'])
                ->get()
                ->keyBy(fn (Holiday $holiday) => $holiday->holiday_date->toDateString());

            foreach ($validRows as $date => $title) {
                Holiday::createOrUpdateHoliday(['holiday_date' => $date, 'title' => $title], $existing->get($date));

                $existing->has($date) ? $this->updatedCount++ : $this->createdCount++;
            }
        });
    }

    /**
     * The day a cell holds as Y-m-d, or null when it is not a date.
     */
    private function parseDate(mixed $value): ?string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $value = trim((string) $value);

        // a date cell of a spreadsheet arrives as its serial number: the days since 1900
        if (is_numeric($value)) {
            $date = Date::excelToDateTimeObject((float) $value)->format('Y-m-d');

            return $date >= '2000-01-01' && $date <= '2100-12-31' ? $date : null;
        }

        foreach (self::DATE_FORMATS as $format) {
            try {
                $date = Carbon::createFromFormat('!'.$format, $value);
            } catch (\Throwable) {
                continue;
            }

            // a day that does not exist, like 31/02, rolls over to another date instead of failing
            if ($date && $date->format($format) === $value) {
                return $date->toDateString();
            }
        }

        return null;
    }

    public function getFailures(): array
    {
        return $this->failures;
    }

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }

    public function getCreatedCount(): int
    {
        return $this->createdCount;
    }

    public function getUpdatedCount(): int
    {
        return $this->updatedCount;
    }
}

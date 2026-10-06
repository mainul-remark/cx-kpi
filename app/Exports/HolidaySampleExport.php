<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * The sample file of the holiday import: its headings and a few rows showing how to fill them.
 */
class HolidaySampleExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function headings(): array
    {
        return ['Date', 'Title'];
    }

    public function array(): array
    {
        $year = today()->year;

        return [
            [$year.'-02-21', 'International Mother Language Day'],
            [$year.'-03-26', 'Independence Day'],
            [$year.'-12-16', 'Victory Day'],
        ];
    }
}

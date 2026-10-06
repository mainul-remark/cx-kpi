<?php

namespace Database\Seeders;

use App\Models\Holiday;
use Illuminate\Database\Seeder;

class HolidaySeeder extends Seeder
{
    /**
     * Office holidays, on top of the weekly off day.
     */
    public function run(): void
    {
        $holidays = [
            ['holiday_date' => '2026-10-20', 'title' => 'Durga Puja'],
            ['holiday_date' => '2026-10-21', 'title' => 'Durga Puja (Bijoya Dashami)'],
            ['holiday_date' => '2026-12-16', 'title' => 'Victory Day'],
            ['holiday_date' => '2026-12-25', 'title' => 'Christmas Day'],
        ];

        foreach ($holidays as $holiday) {
            Holiday::query()->firstOrCreate(
                ['holiday_date' => $holiday['holiday_date']],
                ['title' => $holiday['title']]
            );
        }
    }
}

<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Holiday extends Model
{
    /**
     * The day the office is off every week.
     */
    public const WEEKLY_OFF_DAY = CarbonInterface::FRIDAY;

    /**
     * The day a working week starts on.
     */
    public const WEEK_START_DAY = CarbonInterface::SATURDAY;

    protected $fillable = [
        'holiday_date',
        'title',
    ];

    protected function casts(): array
    {
        return [
            'holiday_date' => 'date:Y-m-d',
        ];
    }

    /**
     * Create a new holiday, or update the given one, from validated data.
     *
     * Targets already set on the day are removed with it, as nobody works on a holiday.
     */
    public static function createOrUpdateHoliday(array $data, ?self $holiday = null): self
    {
        return DB::transaction(function () use ($data, $holiday) {
            $holiday ??= new self();

            $holiday->fill([
                'holiday_date' => $data['holiday_date'],
                'title'        => $data['title'],
            ]);
            $holiday->save();

            DailyTarget::query()->whereDate('target_date', $holiday->holiday_date->toDateString())->delete();

            return $holiday;
        });
    }

    /**
     * The days of a range the office is open on: every day but the weekly off day and the holidays.
     *
     * @return list<string> dates as Y-m-d
     */
    public static function workingDays(string $from, string $to): array
    {
        $holidays = self::query()
            ->whereDate('holiday_date', '>=', $from)
            ->whereDate('holiday_date', '<=', $to)
            ->get(['holiday_date'])
            ->map(fn (self $holiday) => $holiday->holiday_date->toDateString())
            ->flip();

        $days = [];

        foreach (CarbonPeriod::create($from, $to) as $day) {
            if ($day->dayOfWeek !== self::WEEKLY_OFF_DAY && !$holidays->has($day->toDateString())) {
                $days[] = $day->toDateString();
            }
        }

        return $days;
    }
}

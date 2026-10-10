<?php

return [
    // the timezone a work day is counted in; timestamps themselves are stored as the app stores them
    'timezone' => env('ATTENDANCE_TIMEZONE', env('APP_TIMEZONE', 'Asia/Dhaka')),

    // the shift of a user who has none assigned: start as HH:MM in the timezone above, and the minutes of grace
    'default_shift' => [
        'start_time' => env('ATTENDANCE_SHIFT_START', '10:00'),
        'grace_minutes' => (int) env('ATTENDANCE_GRACE_MINUTES', 15),
    ],

    // true refuses a check in made from outside every active office location; false only records where it was made
    'require_office' => (bool) env('ATTENDANCE_REQUIRE_OFFICE', false),

    // the most check-in rows the attendance page lists for a range, so a long range of every user stays light
    'max_listed_sessions' => 500,
];

<?php

return [
    // the timezone a work day is counted in; timestamps themselves are stored as the app stores them
    'timezone' => env('ATTENDANCE_TIMEZONE', env('APP_TIMEZONE', 'Asia/Dhaka')),

    // the most check-in rows the attendance page lists for a range, so a long range of every user stays light
    'max_listed_sessions' => 500,
];

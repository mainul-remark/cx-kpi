<?php

return [
    // the timezone a work day is counted in; timestamps themselves are stored as the app stores them
    'timezone' => env('ATTENDANCE_TIMEZONE', env('APP_TIMEZONE', 'Asia/Dhaka')),

    // how many rows a page of the attendance sheet and of the check-in log holds
    'per_page' => 25,
];

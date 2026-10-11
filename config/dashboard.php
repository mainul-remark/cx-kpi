<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cache Time
    |--------------------------------------------------------------------------
    |
    | Seconds the per-user figures of the dashboard (KPI scores and attendance
    | of every field user) are kept for a range, so opening the page, paging
    | and sorting do not work the whole team out again each time. 0 turns it
    | off. The refresh button of the page always works the figures out afresh.
    |
    */

    'cache_ttl' => (int) env('DASHBOARD_CACHE_TTL', 60),

    // how many rows a page of the performance table holds
    'per_page' => 10,

    // how many users the "needs attention" and "missing reports" lists show
    'top' => 5,

    // how many users are looked at at once while the team is added up, which bounds the memory used
    'chunk' => 200,

    // the score bands of the distribution, as the lowest score of each band, best first
    'score_bands' => [
        'excellent' => 90,
        'good' => 70,
        'fair' => 50,
    ],
];

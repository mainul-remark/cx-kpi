<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Activity Weights
    |--------------------------------------------------------------------------
    |
    | How much each activity counts towards the KPI of a user. Target and
    | completed counts of an activity are both multiplied by its weight
    | before the KPI is worked out, so with every weight the same each
    | completed task counts equally. A weight of 0 leaves an activity out.
    |
    */

    'weights' => [
        'outbound_calls'  => 1,
        'inbound_calls'   => 1,
        'message_replies' => 1,
        'comment_replies' => 1,
        'project_calls'   => 1,
    ],

];

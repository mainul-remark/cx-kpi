<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Activity Weights
    |--------------------------------------------------------------------------
    |
    | How much each activity counts towards the KPI of a user. Target and
    | completed counts of an activity are both multiplied by its weight
    | before the KPI is worked out. A weight of 0 leaves an activity out
    | of the score, it is still shown next to its target.
    |
    | Only the outbound calls are scored: the outbound calls and the order
    | processing calls reported are added up and compared with the outbound
    | call target. Inbound calls, comments and message replies can't be
    | planned for, their targets are approximate and informational only.
    |
    */

    'weights' => [
        'outbound_calls'      => 1,
        'inbound_calls'       => 0,
        'comments'            => 0,
        'message_replies'     => 0,
        'entity_inbound_calls' => 0,
    ],

];

<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Outbound Calls Share
    |--------------------------------------------------------------------------
    |
    | The part of the 100 points of the KPI the outbound calls carry, in
    | percent. The outbound calls and the order processing calls reported
    | are added up and compared with the outbound call target, and the
    | achievement is capped at 100%, so it can never earn more than this.
    |
    | The rest is split equally among the other activities the user has a
    | target for (inbound calls, and the comments, message replies and
    | inbound calls of each platform and project). A user with an outbound
    | call target alone is scored out of the full 100, and one with no
    | outbound call target shares the 100 equally. The exported workbook
    | holds these as editable weights, an admin can change them there.
    |
    */

    'outbound_share' => 70,

    /*
    |--------------------------------------------------------------------------
    | Target Mode
    |--------------------------------------------------------------------------
    |
    | daily  - targets are set for each day (daily_targets) and matched with
    |          the report of that day.
    | period - one target per user per month and activity (period_targets),
    |          matched with everything reported in the range. In a range of
    |          less than a month the target is prorated by the working days
    |          of the range, less the days on leave and before the user joined.
    |
    */

    'target_mode' => env('KPI_TARGET_MODE', 'daily'),

    /*
    |--------------------------------------------------------------------------
    | Activity Weights
    |--------------------------------------------------------------------------
    |
    | Which activities add up to the target and actual totals and the percent
    | shown with them (not to the score, which is worked out from the share
    | of each activity above). Target and completed counts of an activity are
    | multiplied by its weight, a weight of 0 leaves an activity out of those
    | totals, it is still shown next to its target.
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

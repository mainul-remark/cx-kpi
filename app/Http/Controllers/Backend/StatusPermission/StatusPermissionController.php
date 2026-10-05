<?php

namespace App\Http\Controllers\Backend\StatusPermission;

use App\Http\Controllers\Controller;

/**
 * Virtual controller — no routes attached.
 *
 * This controller exists solely as a namespace for ACL resource registration.
 * Each status slug gets a corresponding ACL resource pointing to this controller:
 *
 *   StatusPermissionController @ changeToProcessing
 *   StatusPermissionController @ changeToSolved
 *   StatusPermissionController @ changeToRejected
 *   ...
 *
 * These are auto-created via StatusObserver when a Status record is saved.
 * Assign them to roles via the ACL UI at /role.
 *
 * Checking permission:
 *   Controller : app(StatusPermissionService::class)->authorize('processing')
 *   Blade      : @allowed([StatusPermissionController::class, 'changeToProcessing'])
 */
class StatusPermissionController extends Controller
{
    //
}

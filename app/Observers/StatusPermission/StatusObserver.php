<?php

namespace App\Observers\StatusPermission;

use App\Http\Controllers\Backend\StatusPermission\StatusPermissionController;
use App\Models\StatusPermission\Status;
use Illuminate\Support\Str;
use Uzzal\Acl\Models\Resource;

class StatusObserver
{
    /**
     * Auto-create an ACL resource when a new status is created.
     * This makes it appear in the ACL role/permission management UI.
     */
    public function created(Status $status): void
    {
        $fullAction = StatusPermissionController::class . '@' . $status->aclAction();

        Resource::firstOrCreate(
            [
                'controller' => StatusPermissionController::class,
                'action'     => $fullAction,
            ],
            [
                'resource_id' => (string) Str::uuid(),
                'name'        => 'Change status to ' . $status->label,
                'controller'  => StatusPermissionController::class,
                'action'      => $fullAction,
            ]
        );
    }

    /**
     * Sync label changes to the ACL resource name.
     */
    public function updated(Status $status): void
    {
        if ($status->isDirty('label')) {
            $fullAction = StatusPermissionController::class . '@' . $status->aclAction();
            Resource::where('controller', StatusPermissionController::class)
                ->where('action', $fullAction)
                ->update(['name' => 'Change status to ' . $status->label]);
        }
    }

    /**
     * Remove the ACL resource when a status is deleted.
     */
    public function deleted(Status $status): void
    {
        $fullAction = StatusPermissionController::class . '@' . $status->aclAction();
        Resource::where('controller', StatusPermissionController::class)
            ->where('action', $fullAction)
            ->delete();
    }
}

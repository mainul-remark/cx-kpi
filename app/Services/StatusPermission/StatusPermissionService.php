<?php

namespace App\Services\StatusPermission;

use App\Http\Controllers\Backend\StatusPermission\StatusPermissionController;
use App\Models\StatusPermission\Status;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StatusPermissionService
{
    /**
     * Check if the given user can change to the given status slug.
     *
     * Usage:
     *   app(StatusPermissionService::class)->can(auth()->user(), 'processing')
     */
    public function can(User $user, string $statusSlug): bool
    {
        $action = 'changeTo' . Str::studly($statusSlug);
        return (bool) allowed([StatusPermissionController::class, $action]);
    }

    /**
     * Return all status slugs the current user is allowed to change to.
     *
     * Usage:
     *   app(StatusPermissionService::class)->allowedStatuses()
     */
    public function allowedStatuses(): Collection
    {
        return Status::active()
            ->filter(fn(Status $status) => $this->can(auth()->user(), $status->slug))
            ->values();
    }

    /**
     * Abort with 403 if the current user cannot change to the given status.
     *
     * Usage:
     *   app(StatusPermissionService::class)->authorize('processing')
     */
    public function authorize(string $statusSlug): void
    {
        abort_unless(
            $this->can(auth()->user(), $statusSlug),
            403,
            'You do not have permission to set this status.'
        );
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AttendanceSession;
use App\Models\User;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceCheckController extends Controller
{
    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    /** The state the header button shows: whether the user is checked in, and any forgotten checkout to warn about. */
    public function status(Request $request): JsonResponse
    {
        $user = $this->fieldUser($request);

        $this->attendance->closeStale($user->id);

        return response()->json($this->state($user, $this->attendance->pendingWarnings($user)->all()));
    }

    public function checkIn(Request $request): JsonResponse
    {
        $user = $this->fieldUser($request);

        $result = $this->attendance->checkIn($user, $this->geo($request), $request->ip(), $request->userAgent());

        return response()->json($this->state($user, $result['warnings']->all()) + [
            'message' => $result['created'] ? 'You are checked in.' : 'You are already checked in.',
        ]);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $user = $this->fieldUser($request);

        $session = $this->attendance->checkOut($user, $this->geo($request), $request->ip(), $request->userAgent());

        return response()->json($this->state($user) + [
            'message' => $session ? 'You are checked out. Have a good evening.' : 'You are not checked in.',
        ]);
    }

    /** The user saw the forgotten-checkout warning. */
    public function acknowledge(Request $request): JsonResponse
    {
        $this->attendance->acknowledge($this->fieldUser($request));

        return response()->json(['success' => true]);
    }

    private function fieldUser(Request $request): User
    {
        $user = $request->user();

        abort_unless($user->usages_sector === 'field', 403, 'Only field users check in and out.');

        return $user;
    }

    /** Location is optional, so a missing reading is dropped and never blocks the check. */
    private function geo(Request $request): array
    {
        $data = $request->validate([
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy' => ['nullable', 'numeric', 'min:0'],
        ]);

        return isset($data['lat'], $data['lng']) ? $data : [];
    }

    private function state(User $user, array $warnings = []): array
    {
        $session = $this->attendance->currentSession($user);

        return [
            'checked_in' => (bool) $session,
            'checked_in_at' => $session?->checked_in_at?->toIso8601String(),
            'reminder' => $this->attendance->hasPendingReminder($user),
            'warnings' => array_map(
                fn (AttendanceSession $forgotten) => $forgotten->work_date->format('d M Y'),
                $warnings
            ),
        ];
    }
}

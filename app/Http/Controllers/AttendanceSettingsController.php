<?php

namespace App\Http\Controllers;

use App\Models\OfficeLocation;
use App\Models\User;
use App\Models\WorkShift;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AttendanceSettingsController extends Controller
{
    /**
     * The shifts and office locations the check in rules are judged by.
     */
    public function index(Request $request)
    {
        $this->managerOnly($request);

        return view('backend.attendance.settings', [
            'shifts' => WorkShift::query()->withCount('users')->orderBy('start_time')->orderBy('name')->get(),
            'offices' => OfficeLocation::query()->orderBy('name')->get(),
            'users' => User::query()->where('usages_sector', 'field')->orderBy('name')->get(['id', 'name', 'work_shift_id']),
            'defaultShift' => config('attendance.default_shift'),
            'requireOffice' => config('attendance.require_office'),
        ]);
    }

    public function storeShift(Request $request): JsonResponse
    {
        $this->managerOnly($request);

        WorkShift::create($this->shiftData($request));

        return $this->saved('The shift was added.');
    }

    public function updateShift(Request $request, WorkShift $shift): JsonResponse
    {
        $this->managerOnly($request);

        $shift->update($this->shiftData($request, $shift));

        return $this->saved('The shift was updated.');
    }

    public function destroyShift(Request $request, WorkShift $shift): JsonResponse
    {
        $this->managerOnly($request);

        // its users fall back to the default shift
        $shift->delete();

        return $this->saved('The shift was deleted.');
    }

    /** Set exactly which users work the shift; a user can have one shift only. */
    public function assignShift(Request $request, WorkShift $shift): JsonResponse
    {
        $this->managerOnly($request);

        $data = $request->validate([
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->where('usages_sector', 'field')],
        ]);
        $ids = $data['user_ids'] ?? [];

        User::query()->where('work_shift_id', $shift->id)->whereNotIn('id', $ids)->update(['work_shift_id' => null]);
        User::query()->whereIn('id', $ids)->update(['work_shift_id' => $shift->id]);

        return $this->saved('The shift was assigned.');
    }

    public function storeOffice(Request $request): JsonResponse
    {
        $this->managerOnly($request);

        OfficeLocation::create($this->officeData($request));

        return $this->saved('The office location was added.');
    }

    public function updateOffice(Request $request, OfficeLocation $office): JsonResponse
    {
        $this->managerOnly($request);

        $office->update($this->officeData($request));

        return $this->saved('The office location was updated.');
    }

    public function destroyOffice(Request $request, OfficeLocation $office): JsonResponse
    {
        $this->managerOnly($request);

        $office->delete();

        return $this->saved('The office location was deleted.');
    }

    private function managerOnly(Request $request): void
    {
        abort_unless($request->user()->usages_sector === 'corporate', 403);
    }

    private function saved(string $message): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message]);
    }

    private function shiftData(Request $request, ?WorkShift $shift = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('work_shifts', 'name')->ignore($shift?->id)],
            'start_time' => ['required', 'date_format:H:i'],
            'grace_minutes' => ['required', 'integer', 'between:0,240'],
        ]);
    }

    private function officeData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'lat' => ['nullable', 'numeric', 'between:-90,90', 'required_with:lng'],
            'lng' => ['nullable', 'numeric', 'between:-180,180', 'required_with:lat'],
            'radius_m' => ['required', 'integer', 'between:10,50000'],
            'allowed_ips' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $ips = array_values(array_filter(array_map('trim', explode(',', (string) ($data['allowed_ips'] ?? '')))));

        foreach ($ips as $ip) {
            [$address, $mask] = array_pad(explode('/', $ip, 2), 2, null);

            if (!filter_var($address, FILTER_VALIDATE_IP) || ($mask !== null && !ctype_digit($mask))) {
                throw ValidationException::withMessages(['allowed_ips' => "\"{$ip}\" is not an IP address or a range."]);
            }
        }

        // the office has to be recognisable by a point or by an address
        if (!isset($data['lat']) && $ips === []) {
            throw ValidationException::withMessages(['lat' => 'Give the location of the office, or the IP addresses of it.']);
        }

        $data['allowed_ips'] = $ips ? implode(', ', $ips) : null;
        $data['is_active'] = $request->boolean('is_active', true);

        return $data;
    }
}

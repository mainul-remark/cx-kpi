<?php

namespace App\Http\Controllers;

use App\Models\SocialPlatform;
use Illuminate\Http\Request;
use App\Http\Requests\SocialPlatformRequest;
use Yajra\DataTables\DataTables;

class SocialPlatformController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.social-platforms.index');
        }

        $socialPlatforms = SocialPlatform::query()
            ->select(['id', 'name', 'notes', 'slug', 'active', 'created_at'])
            // newest first until the user sorts by a column
            ->when(!$request->has('order'), fn ($query) => $query->latest());

        return DataTables::of($socialPlatforms)
            ->addIndexColumn()
            ->toJson();
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SocialPlatformRequest $request)
    {
        try {
            $socialPlatform = SocialPlatform::createOrUpdateSocialPlatform($request->validated());
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create social platform. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Social platform created successfully',
            'data' => $socialPlatform,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(SocialPlatform $socialPlatform)
    {
        return response()->json($socialPlatform);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SocialPlatform $socialPlatform)
    {
        return response()->json($socialPlatform);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SocialPlatformRequest $request, SocialPlatform $socialPlatform)
    {
        try {
            $socialPlatform = SocialPlatform::createOrUpdateSocialPlatform($request->validated(), $socialPlatform);
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update social platform. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Social platform updated successfully',
            'data' => $socialPlatform,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(SocialPlatform $socialPlatform)
    {
        if ($socialPlatform->dailyReportReplies()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This social platform has daily report data. Deactivate it instead of deleting it.',
            ], 409);
        }

        try {
            $socialPlatform->delete();
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete social platform. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Social platform deleted successfully',
        ]);
    }
}

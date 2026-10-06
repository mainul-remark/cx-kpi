<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;
use App\Http\Requests\ProjectRequest;
use Yajra\DataTables\DataTables;

class ProjectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.projects.index');
        }

        $projects = Project::query()
            ->select(['id', 'name', 'notes', 'slug', 'active', 'created_at'])
            // newest first until the user sorts by a column
            ->when(!$request->has('order'), fn ($query) => $query->latest());

        return DataTables::of($projects)
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
    public function store(ProjectRequest $request)
    {
        try {
            $project = Project::createOrUpdateProject($request->validated());
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create project. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Project created successfully',
            'data' => $project,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Project $project)
    {
        return response()->json($project);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Project $project)
    {
        return response()->json($project);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProjectRequest $request, Project $project)
    {
        try {
            $project = Project::createOrUpdateProject($request->validated(), $project);
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update project. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully',
            'data' => $project,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Project $project)
    {
        if ($project->dailyReportCalls()->exists() || $project->dailyTargetCalls()->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'This project has daily report or target data. Deactivate it instead of deleting it.',
            ], 409);
        }

        try {
            $project->delete();
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete project. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Project deleted successfully',
        ]);
    }
}

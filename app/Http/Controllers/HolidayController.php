<?php

namespace App\Http\Controllers;

use App\Exports\HolidaySampleExport;
use App\Http\Requests\HolidayImportRequest;
use App\Http\Requests\HolidayRequest;
use App\Imports\HolidaysImport;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\DataTables;

class HolidayController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        if (!$request->ajax()) {
            return view('backend.holidays.index');
        }

        $holidays = Holiday::query()
            ->select(['id', 'holiday_date', 'title', 'created_at'])
            // upcoming and latest first until the user sorts by a column
            ->when(!$request->has('order'), fn ($query) => $query->orderByDesc('holiday_date'));

        return DataTables::of($holidays)
            ->addIndexColumn()
            ->toJson();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(HolidayRequest $request)
    {
        try {
            $holiday = Holiday::createOrUpdateHoliday($request->validated());
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create holiday. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Holiday created successfully',
            'data' => $holiday,
        ], 201);
    }

    /**
     * Import holidays from an uploaded csv or Excel file. Nothing is saved when a row is invalid.
     */
    public function import(HolidayImportRequest $request)
    {
        $import = new HolidaysImport();

        try {
            Excel::import($import, $request->file('file'));
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'The file could not be read. Please use the sample file as a guide.',
            ], 500);
        }

        if ($import->hasFailures()) {
            return response()->json([
                'success' => false,
                'message' => 'Import failed. Please fix the listed rows and re-upload.',
                'errors' => $import->getFailures(),
            ], 422);
        }

        $created = $import->getCreatedCount();
        $updated = $import->getUpdatedCount();

        if ($created + $updated === 0) {
            return response()->json([
                'success' => false,
                'message' => 'No holiday found in the file. It needs a "Date" and a "Title" column, like the sample file.',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "Import successful. {$created} holiday(s) added, {$updated} updated.",
            'created_count' => $created,
            'updated_count' => $updated,
        ]);
    }

    /**
     * Download the sample file of the import, as xlsx unless csv is asked for.
     */
    public function sample(Request $request)
    {
        return $request->query('format') === 'csv'
            ? Excel::download(new HolidaySampleExport(), 'import-holidays.csv', ExcelFormat::CSV)
            : Excel::download(new HolidaySampleExport(), 'import-holidays.xlsx', ExcelFormat::XLSX);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Holiday $holiday)
    {
        return response()->json($holiday);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(HolidayRequest $request, Holiday $holiday)
    {
        try {
            $holiday = Holiday::createOrUpdateHoliday($request->validated(), $holiday);
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update holiday. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Holiday updated successfully',
            'data' => $holiday,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Holiday $holiday)
    {
        try {
            $holiday->delete();
        } catch (\Throwable $th) {
            report($th);
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete holiday. Please try again.',
            ], 500);
        }
        return response()->json([
            'success' => true,
            'message' => 'Holiday deleted successfully',
        ]);
    }
}

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\CommonPages\AdminViewController;
use App\Http\Controllers\Backend\SiteSettingsController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\SocialPlatformController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\DailyTargetController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\UserLeaveController;
use App\Http\Controllers\EmployeeKpiController;
use App\Http\Controllers\LeaveRequestController;

Route::get('/', function () {
    if (auth()->check())
        return redirect('/dashboard');
    else
        return redirect('/login');
})->name('/');

Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'resource.maker',
    'auth.acl',
])->group(function () {
    Route::get('/dashboard', [AdminViewController::class, 'dashboard'])->name('admin.dashboard');
    Route::get('/view-permissions', [AdminViewController::class, 'viewPermissionList'])->name('admin.view-permissions');
    Route::post('/update-resource-label/{resource_id}', [AdminViewController::class, 'updateResourceLabel'])->name('admin.update-resource-label');
    Route::get('/admin/activity-log', [AdminViewController::class, 'activityLog'])->name('admin.activity-logs');

    Route::prefix('admin')->middleware(['resource.maker','auth.acl'])->group(function () {
        Route::post('/users/import', [UsersController::class, 'import'])->name('users.import');
        Route::resource('/roles',RoleController::class);
        Route::resource('/users',UsersController::class);
    });
    // registered before the resource so "team" is not read as a report id
    Route::get('daily-reports/team', [DailyReportController::class, 'team'])->name('daily-reports.team');
    Route::resources([
        'site-settings'             => SiteSettingsController::class,
        'projects'                  => ProjectController::class,
        'social-platforms'          => SocialPlatformController::class,
        'daily-reports'             => DailyReportController::class,
    ]);
    Route::resource('daily-targets', DailyTargetController::class)->only(['index', 'create', 'store', 'edit', 'destroy']);
    Route::post('holidays/import', [HolidayController::class, 'import'])->name('holidays.import');
    Route::get('holidays/sample', [HolidayController::class, 'sample'])->name('holidays.sample');
    Route::resource('holidays', HolidayController::class)->except(['create', 'show']);
    // named, as "leaves" would otherwise be bound as "leaf"
    Route::resource('leaves', UserLeaveController::class)->except(['create', 'show'])->parameters(['leaves' => 'leave']);
    Route::post('leaves/{leave}/approve', [UserLeaveController::class, 'approve'])->name('leaves.approve');
    Route::post('leaves/{leave}/reject', [UserLeaveController::class, 'reject'])->name('leaves.reject');
    Route::resource('my-leaves', LeaveRequestController::class)->only(['index', 'store', 'destroy'])->parameters(['my-leaves' => 'leave']);
    Route::get('attendance', [AttendanceController::class, 'index'])->name('attendance.index');
    Route::get('kpi', [EmployeeKpiController::class, 'index'])->name('kpi.index');
    // registered before the user route so "export" and "monthly" are not read as a user id
    Route::get('kpi/export', [EmployeeKpiController::class, 'export'])->name('kpi.export');
    Route::get('kpi/monthly', [EmployeeKpiController::class, 'monthly'])->name('kpi.monthly');
    Route::get('kpi/{user}', [EmployeeKpiController::class, 'show'])->name('kpi.show');
    Route::post('site-settings/theme', [SiteSettingsController::class, 'saveTheme'])->name('site-settings.theme');
});

//Route::get('/phpinfo', function () {return phpinfo();});
Route::get('/optimize-clear', function () {return \Mainul\CustomHelperFunctions\Helpers\CustomHelper::optimizeClear();});


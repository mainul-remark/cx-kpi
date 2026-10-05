<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Backend\CommonPages\AdminViewController;
use App\Http\Controllers\Backend\SiteSettingsController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\UsersController;
use App\Http\Controllers\ProjectController;

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
    Route::resources([
        'site-settings'             => SiteSettingsController::class,
        'projects'                  => ProjectController::class,
    ]);
    Route::post('site-settings/theme', [SiteSettingsController::class, 'saveTheme'])->name('site-settings.theme');
});

//Route::get('/phpinfo', function () {return phpinfo();});
Route::get('/optimize-clear', function () {return \Mainul\CustomHelperFunctions\Helpers\CustomHelper::optimizeClear();});


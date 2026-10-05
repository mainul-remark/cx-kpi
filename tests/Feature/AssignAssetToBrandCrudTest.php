<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\Asset\AssignAssetToBrandController;
use App\Models\AssignAssetToBrand;
use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Uzzal\Acl\Middleware\AuthenticateWithAcl;
use Uzzal\Acl\Middleware\ResourceMaker;

class AssignAssetToBrandCrudTest extends TestCase
{
    public function test_assign_asset_to_brand_blade_contains_crud_hooks(): void
    {
        $blade = file_get_contents(resource_path('views/backend/asset-management/asset-assign-to-brand.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('id="assignmentForm"', $blade);
        $this->assertStringContainsString('id="viewAssignmentModal"', $blade);
        $this->assertStringContainsString('id="pagination-links"', $blade);
        $this->assertStringContainsString('id="modal-asset"', $blade);
        $this->assertStringContainsString("route('assets.assign-asset-to-brand.assets')", $blade);
        $this->assertStringNotContainsString('id="modal-asset-charge"', $blade);
        $this->assertStringNotContainsString('id="modal-close-date"', $blade);
    }

    public function test_store_endpoint_validates_and_persists_multiple_assignments_for_one_asset(): void
    {
        $originalDefaultConnection = config('database.default');
        $this->bootSqliteAssignmentTables();

        try {
            $invalidResponse = $this->withoutMiddleware()->postJson('/asset/assign-asset-to-brand', [
                'brand_ids' => [],
                'asset_id' => '',
                'status' => 0,
            ]);

            $invalidResponse->assertStatus(422);
            $invalidResponse->assertJsonValidationErrors([
                'brand_ids',
                'asset_id',
            ]);

            $validResponse = $this->withoutMiddleware()->postJson('/asset/assign-asset-to-brand', [
                'brand_ids' => [1, 2],
                'asset_id' => 1,
                'status' => 1,
            ]);

            $validResponse->assertOk();
            $validResponse->assertJsonPath('success', true);
            $validResponse->assertJsonPath('created_count', 2);
            $validResponse->assertJsonCount(2, 'data');
            $validResponse->assertJsonPath('data.0.asset.asset_code', '50000001');
            $validResponse->assertJsonPath('data.0.asset_charge', 0);
            $validResponse->assertJsonPath('data.0.close_date', null);
            $validResponse->assertJsonPath('data.0.status', 1);
            $validResponse->assertJsonPath('data.0.is_asset_assigned_currently', 1);

            $this->assertDatabaseHas('assign_asset_to_brands', [
                'brand_id' => 1,
                'asset_id' => 1,
                'assigned_by_user_id' => 1,
                'status' => 1,
                'asset_charge' => 0,
                'close_date' => null,
                'is_asset_assigned_currently' => 1,
            ]);

            $this->assertDatabaseHas('assign_asset_to_brands', [
                'brand_id' => 2,
                'asset_id' => 1,
                'assigned_by_user_id' => 1,
                'status' => 1,
                'asset_charge' => 0,
                'close_date' => null,
                'is_asset_assigned_currently' => 1,
            ]);
        } finally {
            $this->dropSqliteAssignmentTables();
            config(['database.default' => $originalDefaultConnection]);
        }
    }

    public function test_filter_endpoint_returns_paginated_results_and_asset_options_respect_filters(): void
    {
        $originalDefaultConnection = config('database.default');
        $this->bootSqliteAssignmentTables();

        try {
            DB::table('assign_asset_to_brands')->insert([
                [
                    'id' => 1,
                    'asset_id' => 1,
                    'brand_id' => 1,
                    'assigned_by_user_id' => 1,
                    'asset_charge' => 100,
                    'close_date' => null,
                    'status' => 1,
                    'is_asset_assigned_currently' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ],
                [
                    'id' => 2,
                    'asset_id' => 1,
                    'brand_id' => 2,
                    'assigned_by_user_id' => 1,
                    'asset_charge' => 100,
                    'close_date' => null,
                    'status' => 1,
                    'is_asset_assigned_currently' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ],
                [
                    'id' => 3,
                    'asset_id' => 2,
                    'brand_id' => 2,
                    'assigned_by_user_id' => 1,
                    'asset_charge' => 200,
                    'close_date' => now()->toDateString(),
                    'status' => 0,
                    'is_asset_assigned_currently' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                    'deleted_at' => null,
                ],
            ]);

            $filterResponse = $this->withoutMiddleware()->getJson('/asset/assign-asset-to-brand/filter?per_page=2&page=1');

            $filterResponse->assertOk();
            $filterResponse->assertJsonCount(2, 'data');
            $filterResponse->assertJsonPath('meta.total', 2);
            $filterResponse->assertJsonPath('meta.current_page', 1);
            $filterResponse->assertJsonPath('meta.last_page', 1);
            $filterResponse->assertJsonPath('data.0.is_asset_assigned_currently', 0);
            $filterResponse->assertJsonPath('data.1.asset_id', 1);
            $filterResponse->assertJsonPath('data.1.assignment_count', 2);
            $filterResponse->assertJsonPath('data.1.can_edit', false);
            $filterResponse->assertJsonPath('data.1.can_delete', false);
            $filterResponse->assertJsonPath('data.1.brand_names', 'Apex, Remark');
            $filterResponse->assertJsonCount(2, 'data.1.brands');

            $assetOptionsResponse = $this->withoutMiddleware()->getJson('/asset/assign-asset-to-brand/assets?division_id=1&asset_type_id=1');

            $assetOptionsResponse->assertOk();
            $assetOptionsResponse->assertJsonCount(1, 'data');
            $assetOptionsResponse->assertJsonPath('data.0.id', 1);
            $assetOptionsResponse->assertJsonPath('data.0.asset_type.name', 'Banner');
            $assetOptionsResponse->assertJsonPath('data.0.store.title', 'Banani');
        } finally {
            $this->dropSqliteAssignmentTables();
            config(['database.default' => $originalDefaultConnection]);
        }
    }

    public function test_show_and_update_endpoints_return_and_modify_assignment_payload_correctly(): void
    {
        $originalDefaultConnection = config('database.default');
        $this->bootSqliteAssignmentTables();

        try {
            DB::table('assign_asset_to_brands')->insert([
                'id' => 1,
                'asset_id' => 1,
                'brand_id' => 1,
                'assigned_by_user_id' => 1,
                'asset_charge' => 100,
                'close_date' => '2026-04-01',
                'status' => 1,
                'is_asset_assigned_currently' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);

            $showResponse = $this->withoutCrudGuards()->getJson('/asset/assign-asset-to-brand/1');

            $showResponse->assertOk();
            $showResponse->assertJsonPath('id', 1);
            $showResponse->assertJsonPath('brand.name', 'Apex');
            $showResponse->assertJsonPath('asset.asset_code', '50000001');
            $showResponse->assertJsonPath('asset_charge', 100);
            $showResponse->assertJsonPath('close_date', '2026-04-01');
            $showResponse->assertJsonPath('is_asset_assigned_currently', 1);

            $updateResponse = $this->withoutCrudGuards()->putJson('/asset/assign-asset-to-brand/1', [
                'brand_id' => 2,
                'asset_id' => 1,
                'status' => 0,
            ]);

            $updateResponse->assertOk();
            $updateResponse->assertJsonPath('success', true);
            $updateResponse->assertJsonPath('data.brand.name', 'Remark');
            $updateResponse->assertJsonPath('data.asset_charge', 100);
            $updateResponse->assertJsonPath('data.status', 0);
            $updateResponse->assertJsonPath('data.is_asset_assigned_currently', 0);
            $updateResponse->assertJsonPath('data.close_date', '2026-04-01');

            $this->assertDatabaseHas('assign_asset_to_brands', [
                'id' => 1,
                'brand_id' => 2,
                'asset_id' => 1,
                'status' => 0,
                'asset_charge' => 100,
                'close_date' => '2026-04-01',
                'is_asset_assigned_currently' => 0,
            ]);
        } finally {
            $this->dropSqliteAssignmentTables();
            config(['database.default' => $originalDefaultConnection]);
        }
    }

    public function test_destroy_marks_assignment_as_not_current_before_soft_delete(): void
    {
        $originalDefaultConnection = config('database.default');
        $this->bootSqliteAssignmentTables();

        try {
            DB::table('assign_asset_to_brands')->insert([
                'id' => 1,
                'asset_id' => 1,
                'brand_id' => 1,
                'assigned_by_user_id' => 1,
                'asset_charge' => 100,
                'close_date' => null,
                'status' => 1,
                'is_asset_assigned_currently' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ]);

            $assignment = AssignAssetToBrand::query()->findOrFail(1);
            $response = app(AssignAssetToBrandController::class)->destroy($assignment);

            $this->assertInstanceOf(JsonResponse::class, $response);
            $this->assertTrue($response->getData(true)['success']);

            $this->assertSoftDeleted('assign_asset_to_brands', [
                'id' => 1,
                'is_asset_assigned_currently' => 0,
            ]);
        } finally {
            $this->dropSqliteAssignmentTables();
            config(['database.default' => $originalDefaultConnection]);
        }
    }

    private function bootSqliteAssignmentTables(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::connection('sqlite')->dropIfExists('assign_asset_to_brands');
        Schema::connection('sqlite')->dropIfExists('assets');
        Schema::connection('sqlite')->dropIfExists('asset_types');
        Schema::connection('sqlite')->dropIfExists('stores');
        Schema::connection('sqlite')->dropIfExists('districts');
        Schema::connection('sqlite')->dropIfExists('divisions');
        Schema::connection('sqlite')->dropIfExists('brands');
        Schema::connection('sqlite')->dropIfExists('users');

        Schema::connection('sqlite')->create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->timestamps();
        });

        Schema::connection('sqlite')->create('divisions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
        });

        Schema::connection('sqlite')->create('districts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('division_id')->nullable();
            $table->string('name');
        });

        Schema::connection('sqlite')->create('stores', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('title');
            $table->string('code')->nullable();
            $table->unsignedBigInteger('division_id')->nullable();
            $table->unsignedBigInteger('district_id')->nullable();
            $table->tinyInteger('status')->default(1)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('sqlite')->create('asset_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('sqlite')->create('assets', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('asset_type_id');
            $table->string('name');
            $table->string('asset_code')->nullable();
            $table->unsignedBigInteger('store_id')->nullable();
            $table->tinyInteger('status')->default(1)->nullable();
            $table->tinyInteger('is_common_asset')->default(0)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('sqlite')->create('brands', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('code')->nullable();
            $table->tinyInteger('status')->default(1)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::connection('sqlite')->create('assign_asset_to_brands', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('asset_id');
            $table->unsignedBigInteger('brand_id');
            $table->unsignedBigInteger('assigned_by_user_id');
            $table->float('asset_charge', 10, 2)->default(0)->nullable();
            $table->string('close_date')->nullable();
            $table->tinyInteger('status')->default(1)->nullable();
            $table->tinyInteger('is_asset_assigned_currently')->default(1)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('divisions')->insert([
            ['id' => 1, 'name' => 'Dhaka'],
            ['id' => 2, 'name' => 'Chattogram'],
        ]);

        DB::table('districts')->insert([
            ['id' => 1, 'division_id' => 1, 'name' => 'Dhaka'],
            ['id' => 2, 'division_id' => 2, 'name' => 'Chattogram'],
        ]);

        DB::table('stores')->insert([
            [
                'id' => 1,
                'title' => 'Banani',
                'code' => 'STR001',
                'division_id' => 1,
                'district_id' => 1,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ],
            [
                'id' => 2,
                'title' => 'GEC',
                'code' => 'STR002',
                'division_id' => 2,
                'district_id' => 2,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ],
        ]);

        DB::table('asset_types')->insert([
            ['id' => 1, 'name' => 'Banner', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null],
            ['id' => 2, 'name' => 'Shelf', 'created_at' => now(), 'updated_at' => now(), 'deleted_at' => null],
        ]);

        DB::table('assets')->insert([
            [
                'id' => 1,
                'asset_type_id' => 1,
                'name' => 'Front Banner',
                'asset_code' => '50000001',
                'store_id' => 1,
                'status' => 1,
                'is_common_asset' => 0,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ],
            [
                'id' => 2,
                'asset_type_id' => 2,
                'name' => 'Display Shelf',
                'asset_code' => '50000002',
                'store_id' => 2,
                'status' => 1,
                'is_common_asset' => 0,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ],
        ]);

        DB::table('brands')->insert([
            [
                'id' => 1,
                'name' => 'Apex',
                'code' => 'APX',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ],
            [
                'id' => 2,
                'name' => 'Remark',
                'code' => 'RMK',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
                'deleted_at' => null,
            ],
        ]);
    }

    private function dropSqliteAssignmentTables(): void
    {
        Schema::connection('sqlite')->dropIfExists('assign_asset_to_brands');
        Schema::connection('sqlite')->dropIfExists('assets');
        Schema::connection('sqlite')->dropIfExists('asset_types');
        Schema::connection('sqlite')->dropIfExists('stores');
        Schema::connection('sqlite')->dropIfExists('districts');
        Schema::connection('sqlite')->dropIfExists('divisions');
        Schema::connection('sqlite')->dropIfExists('brands');
        Schema::connection('sqlite')->dropIfExists('users');
        DB::purge('sqlite');
    }

    private function withoutCrudGuards(): static
    {
        Sanctum::actingAs(User::query()->findOrFail(1));

        return $this->withoutMiddleware(array_values(array_filter([
            config('jetstream.auth_session'),
            EnsureEmailIsVerified::class,
            ResourceMaker::class,
            AuthenticateWithAcl::class,
        ])));
    }
}

<?php

namespace Tests\Feature;

use App\Http\Controllers\Backend\Asset\AssetController;
use App\Http\Requests\Backend\Asset\AssetTypeRequest;
use App\Models\AssetType;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Tests\TestCase;

class AssetTypeModalFromAssetsPageTest extends TestCase
{
    public function test_assets_blade_contains_the_embedded_asset_category_modal_hooks(): void
    {
        $blade = file_get_contents(resource_path('views/backend/asset-management/assets.blade.php'));

        $this->assertIsString($blade);
        $this->assertStringContainsString('open-asset-castegory-modal', $blade);
        $this->assertStringContainsString('id="assetCategoryForm"', $blade);
        $this->assertStringContainsString('assetCategoryApiUrl', $blade);
        $this->assertStringContainsString('btn-save-asset-category', $blade);
        $this->assertStringNotContainsString('id="assetTypeForm"', $blade);
    }

    public function test_asset_type_store_endpoint_returns_the_validation_errors_used_by_the_assets_modal(): void
    {
        $originalDefaultConnection = config('database.default');
        $this->bootSqliteAssetTables();

        try {
            $response = $this->withoutMiddleware()->postJson('/asset-types', [
                'name' => '',
                'has_default_dimension' => 1,
                'height' => '',
                'width' => '',
                'dimension_unit_name' => '',
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors([
                'name',
                'height',
                'width',
                'dimension_unit_name',
            ]);
            $response->assertJsonPath('errors.name.0', 'Asset type name is required.');
            $response->assertJsonPath('errors.height.0', 'Height is required when Default Dimension is enabled.');
            $response->assertJsonPath('errors.width.0', 'Width is required when Default Dimension is enabled.');
            $response->assertJsonPath('errors.dimension_unit_name.0', 'Unit is required when Default Dimension is enabled.');
        } finally {
            $this->dropSqliteAssetTables();
            config(['database.default' => $originalDefaultConnection]);
        }
    }

    public function test_assets_modal_flow_can_create_a_new_asset_type_through_the_existing_endpoint(): void
    {
        $originalDefaultConnection = config('database.default');
        $this->bootSqliteAssetTables(includeStoresTable: true);

        try {
            $response = $this->withoutMiddleware()->postJson('/asset-types', [
                'name' => 'Popup Banner',
                'status' => 1,
                'has_kv_space' => 1,
                'need_asset_image' => 1,
                'has_asset_self' => 1,
                'total_self' => 4,
            ]);

            $response->assertOk();
            $response->assertJsonPath('message', 'Asset type created successfully.');
            $response->assertJsonPath('data.name', 'Popup Banner');
            $response->assertJsonPath('data.code', 'PB');
            $response->assertJsonPath('data.need_asset_image', 1);
            $response->assertJsonPath('data.has_asset_self', 1);
            $response->assertJsonPath('data.total_self', 4);

            $this->assertDatabaseHas('asset_types', [
                'name' => 'Popup Banner',
                'code' => 'PB',
                'need_asset_image' => 1,
                'has_asset_self' => 1,
                'total_self' => 4,
            ]);

            $view = app(AssetController::class)->index(Request::create('/assets', 'GET'));

            $this->assertInstanceOf(View::class, $view);
            $assetTypes = $view->getData()['assetTypes'];
            $createdAssetType = $assetTypes->firstWhere('name', 'Popup Banner');

            $this->assertNotNull($createdAssetType);
            $this->assertSame(1, (int) $createdAssetType->need_asset_image);
            $this->assertSame(1, (int) $createdAssetType->has_asset_self);
            $this->assertSame(4, (int) $createdAssetType->total_self);
        } finally {
            $this->dropSqliteAssetTables();
            config(['database.default' => $originalDefaultConnection]);
        }
    }

    public function test_next_code_endpoint_generates_initials_and_appends_a_numeric_suffix_when_needed(): void
    {
        $originalDefaultConnection = config('database.default');
        $this->bootSqliteAssetTables();

        try {
            DB::table('asset_types')->insert([
                [
                    'id' => 1,
                    'name' => 'Popup Banner',
                    'code' => 'PB',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'id' => 2,
                    'name' => 'Billboard',
                    'code' => 'BIL',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);

            $multiWordResponse = $this->withoutMiddleware()->getJson('/asset-types/next-code?name=Popup%20Banner');
            $singleWordResponse = $this->withoutMiddleware()->getJson('/asset-types/next-code?name=Billboard');

            $multiWordResponse->assertOk();
            $multiWordResponse->assertJsonPath('code', 'PB2');

            $singleWordResponse->assertOk();
            $singleWordResponse->assertJsonPath('code', 'BIL2');
        } finally {
            $this->dropSqliteAssetTables();
            config(['database.default' => $originalDefaultConnection]);
        }
    }

    public function test_code_validation_ignores_the_current_asset_type_when_updating(): void
    {
        $originalDefaultConnection = config('database.default');
        $this->bootSqliteAssetTables();

        try {
            DB::table('asset_types')->insert([
                'id' => 7,
                'name' => 'Billboard',
                'code' => 'BIL',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $assetType = AssetType::query()->findOrFail(7);

            $request = new class($assetType) extends AssetTypeRequest
            {
                public function __construct(private AssetType $assetType)
                {
                }

                public function route($param = null, $default = null): mixed
                {
                    if ($param === 'asset_type') {
                        return $this->assetType;
                    }

                    return parent::route($param, $default);
                }
            };
            $request->initialize([], [], [], [], [], ['REQUEST_METHOD' => 'PUT']);

            $validator = Validator::make([
                'name' => 'Billboard Updated',
                'code' => 'BIL',
            ], $request->rules(), $request->messages());

            $this->assertTrue($validator->passes());
        } finally {
            $this->dropSqliteAssetTables();
            config(['database.default' => $originalDefaultConnection]);
        }
    }

    private function bootSqliteAssetTables(bool $includeStoresTable = false): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);

        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::connection('sqlite')->dropIfExists('asset_types');
        Schema::connection('sqlite')->create('asset_types', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name');
            $table->string('code')->nullable()->unique();
            $table->text('default_image')->nullable();
            $table->decimal('height', 10, 2)->nullable();
            $table->decimal('width', 10, 2)->nullable();
            $table->decimal('depth', 10, 2)->nullable();
            $table->string('dimention_unit_name')->nullable();
            $table->decimal('default_price', 10, 2)->nullable();
            $table->tinyInteger('status')->default(1)->nullable();
            $table->tinyInteger('is_digital')->default(0)->nullable();
            $table->integer('total_self')->nullable();
            $table->tinyInteger('has_kv_space')->default(0)->nullable();
            $table->tinyInteger('has_default_dimension')->default(0)->nullable();
            $table->tinyInteger('need_asset_image')->default(0)->nullable();
            $table->tinyInteger('need_asset_planogram')->default(0)->nullable();
            $table->tinyInteger('has_asset_self')->default(0)->nullable();
            $table->tinyInteger('total_kv_slot')->default(1)->nullable();
            $table->tinyInteger('is_double_side')->default(0)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        if ($includeStoresTable) {
            Schema::connection('sqlite')->dropIfExists('stores');
            Schema::connection('sqlite')->create('stores', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('title');
                $table->string('code')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });

            Schema::connection('sqlite')->dropIfExists('brands');
            Schema::connection('sqlite')->create('brands', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('code')->nullable();
                $table->tinyInteger('status')->default(1)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });

            Schema::connection('sqlite')->dropIfExists('categories');
            Schema::connection('sqlite')->create('categories', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->string('code')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });

            Schema::connection('sqlite')->dropIfExists('key_visuals');
            Schema::connection('sqlite')->create('key_visuals', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('asset_type_id')->nullable();
                $table->string('name');
                $table->string('unique_code')->nullable();
                $table->tinyInteger('status')->default(1)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });

            Schema::connection('sqlite')->dropIfExists('brand_key_visual');
            Schema::connection('sqlite')->create('brand_key_visual', function (Blueprint $table) {
                $table->unsignedBigInteger('brand_id');
                $table->unsignedBigInteger('key_visual_id');
            });

            Schema::connection('sqlite')->dropIfExists('category_key_visual');
            Schema::connection('sqlite')->create('category_key_visual', function (Blueprint $table) {
                $table->unsignedBigInteger('category_id');
                $table->unsignedBigInteger('key_visual_id');
            });

            Schema::connection('sqlite')->dropIfExists('key_visual_sizes');
            Schema::connection('sqlite')->create('key_visual_sizes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->decimal('width', 10, 2)->nullable();
                $table->decimal('height', 10, 2)->nullable();
                $table->string('unit_name')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });

            Schema::connection('sqlite')->dropIfExists('key_visual_files');
            Schema::connection('sqlite')->create('key_visual_files', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name');
                $table->unsignedBigInteger('key_visual_id')->nullable();
                $table->unsignedBigInteger('key_visual_size_id')->nullable();
                $table->string('kv_file')->nullable();
                $table->integer('kv_size')->nullable();
                $table->string('file_type')->nullable();
                $table->tinyInteger('status')->default(1)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    private function dropSqliteAssetTables(): void
    {
        Schema::connection('sqlite')->dropIfExists('key_visual_files');
        Schema::connection('sqlite')->dropIfExists('key_visual_sizes');
        Schema::connection('sqlite')->dropIfExists('category_key_visual');
        Schema::connection('sqlite')->dropIfExists('brand_key_visual');
        Schema::connection('sqlite')->dropIfExists('key_visuals');
        Schema::connection('sqlite')->dropIfExists('categories');
        Schema::connection('sqlite')->dropIfExists('brands');
        Schema::connection('sqlite')->dropIfExists('stores');
        Schema::connection('sqlite')->dropIfExists('asset_types');
        DB::purge('sqlite');
    }
}

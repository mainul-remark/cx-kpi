<?php

namespace App\Console\Commands;

use App\Models\AssignKvToAsset;
use App\Models\KvExpiryNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ExpireKvAssignments extends Command
{
    protected $signature   = 'kv:expire-assignments';
    protected $description = 'Soft-delete KV assignments whose expires_at has passed and log expiry notifications.';

    public function handle(): int
    {
        $today = Carbon::today()->toDateString();

        $expired = AssignKvToAsset::query()
            ->leftJoin('assets', 'assign_kv_to_assets.asset_id', '=', 'assets.id')
            ->leftJoin('asset_types', 'assets.asset_type_id', '=', 'asset_types.id')
            ->leftJoin('stores', 'assets.store_id', '=', 'stores.id')
            ->leftJoin('key_visuals', 'assign_kv_to_assets.key_visual_id', '=', 'key_visuals.id')
            ->select([
                'assign_kv_to_assets.id',
                'assign_kv_to_assets.assigned_date',
                'assign_kv_to_assets.expires_at',
                'assets.name as asset_name',
                'assets.asset_code',
                'stores.title as store_name',
                'stores.code as store_code',
                'key_visuals.name as kv_name',
                'key_visuals.unique_code as kv_code',
            ])
            ->whereNull('assign_kv_to_assets.deleted_at')
            ->whereNotNull('assign_kv_to_assets.expires_at')
            ->whereDate('assign_kv_to_assets.expires_at', '<=', $today)
            ->get();

        if ($expired->isEmpty()) {
            $this->info('No expired KV assignments found.');
            return self::SUCCESS;
        }

        $count = 0;

        DB::transaction(function () use ($expired, &$count) {
            foreach ($expired as $row) {
                // Create notification record before soft-deleting
                KvExpiryNotification::create([
                    'assign_kv_to_asset_id' => $row->id,
                    'asset_name'            => $row->asset_name,
                    'asset_code'            => $row->asset_code,
                    'store_name'            => $row->store_name ?? 'Unknown Store',
                    'store_code'            => $row->store_code,
                    'kv_name'               => $row->kv_name ?? 'Unknown KV',
                    'kv_code'               => $row->kv_code,
                    'assigned_date'         => $row->assigned_date,
                    'expired_on'            => $row->expires_at,
                ]);

                // Soft-delete the assignment
                AssignKvToAsset::where('id', $row->id)->delete();

                $count++;
            }
        });

        $this->info("Expired and unassigned {$count} KV assignment(s). Notifications created.");

        return self::SUCCESS;
    }
}

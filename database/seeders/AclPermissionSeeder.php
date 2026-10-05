<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Uzzal\Acl\Models\Permission;
use Uzzal\Acl\Models\Resource;

class AclPermissionSeeder extends Seeder
{
    /**
     * Seed role 1 with every ACL resource.
     */
    public function run(): void
    {
        $resourceIds = Resource::query()
            ->orderBy('resource_id')
            ->pluck('resource_id')
            ->all();

        if ($resourceIds === []) {
            return;
        }

        $rows = array_map(static fn (string $resourceId) => [
            'role_id' => 1,
            'resource_id' => $resourceId,
        ], $resourceIds);

        foreach (array_chunk($rows, 500) as $chunk) {
            Permission::query()->insertOrIgnore($chunk);
        }
    }
}

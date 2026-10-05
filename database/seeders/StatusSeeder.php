<?php

namespace Database\Seeders;

use App\Models\StatusPermission\Status;
use Illuminate\Database\Seeder;

class StatusSeeder extends Seeder
{
    /**
     * Default project-wide statuses.
     * The StatusObserver will auto-create ACL resources for each one.
     */
    public function run(): void
    {
        $statuses = [
            ['slug' => 'pending',    'label' => 'Pending',    'is_active' => true],
            ['slug' => 'planned',    'label' => 'Planned',    'is_active' => true],
            ['slug' => 'reviewed',   'label' => 'Reviewed',   'is_active' => true],
            ['slug' => 'assigned',   'label' => 'Assigned',   'is_active' => true],
            ['slug' => 'processing', 'label' => 'Processing', 'is_active' => true],
            ['slug' => 'solved',     'label' => 'Solved',     'is_active' => true],
            ['slug' => 'rejected',   'label' => 'Rejected',   'is_active' => true],
        ];

        foreach ($statuses as $status) {
            Status::firstOrCreate(
                ['slug' => $status['slug']],
                $status
            );
        }
    }
}

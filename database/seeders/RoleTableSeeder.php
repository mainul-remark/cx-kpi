<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Uzzal\Acl\Models\Role;

class RoleTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Role::create(['role_id'=>1,'name'=>'super-admin','created_at'=>'2023-05-05 1:01:15','updated_at'=>'2025-05-05 1:01:13']);
        Role::create(['role_id'=>2,'name'=>'system-admin','created_at'=>'2023-05-05 1:01:15','updated_at'=>'2025-05-05 1:01:13']);
        Role::create(['role_id'=>3,'name'=>'ceo','created_at'=>'2023-05-05 1:01:15','updated_at'=>'2025-05-05 1:01:13']);
        Role::create(['role_id'=>4,'name'=>'business-dep','created_at'=>'2023-05-05 1:01:15','updated_at'=>'2025-05-05 1:01:13']);
        Role::create(['role_id'=>5,'name'=>'it-team','created_at'=>'2023-05-05 1:01:15','updated_at'=>'2025-05-05 1:01:13']);
        Role::create(['role_id'=>6,'name'=>'vm-team','created_at'=>'2023-05-05 1:01:15','updated_at'=>'2025-05-05 1:01:13']);
        Role::create(['role_id'=>7,'name'=>'DSM','created_at'=>'2023-05-05 1:01:15','updated_at'=>'2025-05-05 1:01:13']);
        Role::create(['role_id'=>8,'name'=>'ASM','created_at'=>'2023-05-05 1:01:15','updated_at'=>'2025-05-05 1:01:13']);
        Role::create(['role_id'=>9,'name'=>'BA','created_at'=>'2023-05-05 1:01:15','updated_at'=>'2025-05-05 1:01:13']);
    }
}

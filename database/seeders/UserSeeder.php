<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::/*factory()->withPersonalTeam()->*/create([
            'name' => 'Developer',
            'email' => 'developer@remarkhb.com',
            'password' => bcrypt('developer'),
            'employee_id' => 'M9380',
            'usages_sector' => 'corporate',
        ]);
        User::/*factory()->withPersonalTeam()->*/create([
            'name' => 'Super Admin',
            'email' => 'superadmin@remarkhb.com',
            'password' => bcrypt('superadmin'),
            'employee_id' => 'M02',
            'usages_sector' => 'corporate',
        ]);
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->withPersonalTeam()->create();

//        User::factory()->withPersonalTeam()->create([
//            'name' => 'Developer',
//            'email' => 'dev@email.com',
//            'password' => '123',
//        ]);

        // Disable foreign key checks before seeding
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        $this->call([
            UserSeeder::class,
//            BrandSeeder::class,
            RoleTableSeeder::class,
            UserRoleTableSeeder::class,
            AclResourceSeeder::class,
            ResourceSeeder::class,
            AclPermissionSeeder::class,
            SocialPlatformSeeder::class,
            HolidaySeeder::class,
        ]);
        // Re-enable foreign key checks after seeding
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
    }
}

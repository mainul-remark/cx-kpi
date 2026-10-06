<?php

namespace Database\Seeders;

use App\Models\SocialPlatform;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SocialPlatformSeeder extends Seeder
{
    /**
     * Starter social platforms for the daily report form.
     */
    public function run(): void
    {
        $platforms = ['Facebook', 'Instagram', 'Twitter', 'WhatsApp', 'TikTok', 'YouTube', 'LinkedIn'];

        foreach ($platforms as $name) {
            SocialPlatform::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'active' => true]
            );
        }
    }
}

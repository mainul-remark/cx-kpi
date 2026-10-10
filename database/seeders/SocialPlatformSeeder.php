<?php

namespace Database\Seeders;

use App\Models\SocialPlatform;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class SocialPlatformSeeder extends Seeder
{
    /**
     * Starter social platforms for the daily report form, each taking outbound calls, comments and message replies.
     */
    public function run(): void
    {
        $platforms = ['Facebook', 'Instagram', 'WhatsApp', 'TikTok',];

        foreach ($platforms as $name) {
            SocialPlatform::query()->firstOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'active' => true,
                    'has_outbound_calls' => false,
                    'has_comments' => true,
                    'has_message_replies' => true,
                ]
            );
        }
    }
}

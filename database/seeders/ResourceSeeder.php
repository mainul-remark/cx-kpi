<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Uzzal\Acl\Models\Resource;

class ResourceSeeder extends Seeder
{
    /**
     * Seed the resources table with a snapshot of the current ACL resources
     * (including manually curated labels) captured from the live database.
     */
    public function run(): void
    {
        $timestamp = now();

        $rows = [
            [
                'resource_id' => 'db553504f9a89d6d91de08ef50cc483a39a56f99',
                'name' => 'Admin-Role GET::Create',
                'controller' => 'Admin-Role',
                'action' => 'App\\Http\\Controllers\\Admin\\RoleController@create',
                'label' => 'Role Create Form',
            ],
            [
                'resource_id' => 'a3c814015e93225e275bcd5891f077702f050540',
                'name' => 'Admin-Role DELETE::Destroy',
                'controller' => 'Admin-Role',
                'action' => 'App\\Http\\Controllers\\Admin\\RoleController@destroy',
                'label' => 'Delete Role',
            ],
            [
                'resource_id' => '334b1102918229af8211b26d65330503dad99d92',
                'name' => 'Admin-Role GET::Edit',
                'controller' => 'Admin-Role',
                'action' => 'App\\Http\\Controllers\\Admin\\RoleController@edit',
                'label' => 'Role Edit Form',
            ],
            [
                'resource_id' => '4601341a1d63b12655253f124c2e06fbaba5e4a3',
                'name' => 'Admin-Role GET::Index',
                'controller' => 'Admin-Role',
                'action' => 'App\\Http\\Controllers\\Admin\\RoleController@index',
                'label' => 'Show All Roles',
            ],
            [
                'resource_id' => '65cc3c46eb10884a9e54800d4db6b5df5d3d0174',
                'name' => 'Admin-Role GET::Show',
                'controller' => 'Admin-Role',
                'action' => 'App\\Http\\Controllers\\Admin\\RoleController@show',
                'label' => 'View Single Role',
            ],
            [
                'resource_id' => '3a39f46d28137af620ebada644311838add0aca8',
                'name' => 'Admin-Role POST::Store',
                'controller' => 'Admin-Role',
                'action' => 'App\\Http\\Controllers\\Admin\\RoleController@store',
                'label' => 'Store Role Data',
            ],
            [
                'resource_id' => '0cedac5856f03fb9dd30fc45e585886029272b08',
                'name' => 'Admin-Role PUT|PATCH::Update',
                'controller' => 'Admin-Role',
                'action' => 'App\\Http\\Controllers\\Admin\\RoleController@update',
                'label' => 'Update Role Data',
            ],
            [
                'resource_id' => '0eb2c7a28ba3263e86b0e2d43503a5531aad265d',
                'name' => 'Admin-Users GET::Create',
                'controller' => 'Admin-Users',
                'action' => 'App\\Http\\Controllers\\Admin\\UsersController@create',
                'label' => 'User Create Form',
            ],
            [
                'resource_id' => '8bb512cb819e0bee26d708552f507fb54a31ddde',
                'name' => 'Admin-Users DELETE::Destroy',
                'controller' => 'Admin-Users',
                'action' => 'App\\Http\\Controllers\\Admin\\UsersController@destroy',
                'label' => 'Delete User',
            ],
            [
                'resource_id' => '7a3e22c0f1c1a6aa0c1291214a48620cb696bdeb',
                'name' => 'Admin-Users GET::Edit',
                'controller' => 'Admin-Users',
                'action' => 'App\\Http\\Controllers\\Admin\\UsersController@edit',
                'label' => 'User Edit Form',
            ],
            [
                'resource_id' => '687a191d464b3319a11f63e1aedc03d07a53dc4e',
                'name' => 'Admin-Users POST::Import',
                'controller' => 'Admin-Users',
                'action' => 'App\\Http\\Controllers\\Admin\\UsersController@import',
                'label' => 'Import User',
            ],
            [
                'resource_id' => 'f016cdf8a6bf94a80d70f059d267bffbf7761688',
                'name' => 'Admin-Users GET::Index',
                'controller' => 'Admin-Users',
                'action' => 'App\\Http\\Controllers\\Admin\\UsersController@index',
                'label' => 'Show Users List',
            ],
            [
                'resource_id' => '98cada14798fdda097a44af876adef67ed132892',
                'name' => 'Admin-Users GET::Show',
                'controller' => 'Admin-Users',
                'action' => 'App\\Http\\Controllers\\Admin\\UsersController@show',
                'label' => 'View Single User Data',
            ],
            [
                'resource_id' => 'ab513f32a39162e1d04dc3eecd8b8abd0a111c81',
                'name' => 'Admin-Users POST::Store',
                'controller' => 'Admin-Users',
                'action' => 'App\\Http\\Controllers\\Admin\\UsersController@store',
                'label' => 'Store Post Data',
            ],
            [
                'resource_id' => '8264bf79cb3e51846c2b6d73763b454a39cca93b',
                'name' => 'Admin-Users PUT|PATCH::Update',
                'controller' => 'Admin-Users',
                'action' => 'App\\Http\\Controllers\\Admin\\UsersController@update',
                'label' => 'Update User Data',
            ],
            [
                'resource_id' => 'c1b0940eba6a8193906e9ca343220d20115e09c3',
                'name' => 'Attendance GET::Index',
                'controller' => 'Attendance',
                'action' => 'App\\Http\\Controllers\\AttendanceController@index',
                'label' => 'Show Attendance',
            ],
            [
                'resource_id' => 'e98b6f5f85f88dc0c48e0821e9a5979de927c8e9',
                'name' => 'Backend-CommonPages-AdminView GET::ActivityLog',
                'controller' => 'Backend-CommonPages-AdminView',
                'action' => 'App\\Http\\Controllers\\Backend\\CommonPages\\AdminViewController@activityLog',
                'label' => 'Show Activity Log',
            ],
            [
                'resource_id' => '6be5492e143a906be472cfcb8b9eccd6dab910ac',
                'name' => 'Backend-CommonPages-AdminView GET::Dashboard',
                'controller' => 'Backend-CommonPages-AdminView',
                'action' => 'App\\Http\\Controllers\\Backend\\CommonPages\\AdminViewController@dashboard',
                'label' => 'Show Dashboard',
            ],
            [
                'resource_id' => '36cf1aae8a40d9e51db33e53cd782e408682dcdf',
                'name' => 'Backend-CommonPages-AdminView POST::UpdateResourceLabel',
                'controller' => 'Backend-CommonPages-AdminView',
                'action' => 'App\\Http\\Controllers\\Backend\\CommonPages\\AdminViewController@updateResourceLabel',
                'label' => 'Update Permission Label',
            ],
            [
                'resource_id' => '2d903893b748a9b9e206b238660bfadfb508f4f6',
                'name' => 'Backend-CommonPages-AdminView GET::ViewPermissionList',
                'controller' => 'Backend-CommonPages-AdminView',
                'action' => 'App\\Http\\Controllers\\Backend\\CommonPages\\AdminViewController@viewPermissionList',
                'label' => 'Show Permission List',
            ],
            [
                'resource_id' => '25872440b8776f44c66023ad19eca3cafa20a4a6',
                'name' => 'Backend-SiteSettings GET::Create',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@create',
                'label' => 'Site Setting Create Form',
            ],
            [
                'resource_id' => 'dddbe3dc8805969239c36d790bf59860654e8642',
                'name' => 'Backend-SiteSettings DELETE::Destroy',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@destroy',
                'label' => 'Delete Site Setting',
            ],
            [
                'resource_id' => 'd70f47e60dbe156dd1d6927c440eda47fdb799b1',
                'name' => 'Backend-SiteSettings GET::Edit',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@edit',
                'label' => 'Site Setting Edit Form',
            ],
            [
                'resource_id' => 'b1235011231a66e26104c88994213c6c6b0df364',
                'name' => 'Backend-SiteSettings GET::Index',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@index',
                'label' => 'Show Site Settings',
            ],
            [
                'resource_id' => 'ff0129c8f9ec1ef51a167039952360f6c12ac4ea',
                'name' => 'Backend-SiteSettings POST::SaveTheme',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@saveTheme',
                'label' => 'Save Theme',
            ],
            [
                'resource_id' => '65b9d33ea34c220a68fb9e3fae5565b4fe293d5b',
                'name' => 'Backend-SiteSettings GET::Show',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@show',
                'label' => 'View Single Site Setting',
            ],
            [
                'resource_id' => 'cd546985b26dac182633cc7fcb36e328954c7b75',
                'name' => 'Backend-SiteSettings POST::Store',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@store',
                'label' => 'Store Site Setting Data',
            ],
            [
                'resource_id' => '3dab20db45c360431502fa623f50ad6a128b0f49',
                'name' => 'Backend-SiteSettings PUT|PATCH::Update',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@update',
                'label' => 'Update Site Setting Data',
            ],
            [
                'resource_id' => 'e94d1ba41967ab97b38fc0ab512eacd4ec25ddd4',
                'name' => 'DailyReport GET::Create',
                'controller' => 'DailyReport',
                'action' => 'App\\Http\\Controllers\\DailyReportController@create',
                'label' => 'Daily Report Create Form',
            ],
            [
                'resource_id' => 'c9538408b77caf661981f2b37b5a5bb36f15b4be',
                'name' => 'DailyReport DELETE::Destroy',
                'controller' => 'DailyReport',
                'action' => 'App\\Http\\Controllers\\DailyReportController@destroy',
                'label' => 'Delete Daily Report',
            ],
            [
                'resource_id' => '72d0001887c8b63f1d6d4ad6f84201086713e00e',
                'name' => 'DailyReport GET::Edit',
                'controller' => 'DailyReport',
                'action' => 'App\\Http\\Controllers\\DailyReportController@edit',
                'label' => 'Daily Report Edit Form',
            ],
            [
                'resource_id' => 'b660103b49d7f2a71ea545bf7a79ce47784158c2',
                'name' => 'DailyReport GET::Index',
                'controller' => 'DailyReport',
                'action' => 'App\\Http\\Controllers\\DailyReportController@index',
                'label' => 'Show Own Daily Reports',
            ],
            [
                'resource_id' => 'eb8aa9be48c74c5610a6ab1b655dd9852edc7ec4',
                'name' => 'DailyReport GET::Show',
                'controller' => 'DailyReport',
                'action' => 'App\\Http\\Controllers\\DailyReportController@show',
                'label' => 'View Single Daily Report',
            ],
            [
                'resource_id' => '2ab6ef30493688ab1b8e4f4132a21529e2508ea1',
                'name' => 'DailyReport POST::Store',
                'controller' => 'DailyReport',
                'action' => 'App\\Http\\Controllers\\DailyReportController@store',
                'label' => 'Store Daily Report Data',
            ],
            [
                'resource_id' => '69d20e87873b726c3d6c9151dc3a20c46d1f17db',
                'name' => 'DailyReport GET::Team',
                'controller' => 'DailyReport',
                'action' => 'App\\Http\\Controllers\\DailyReportController@team',
                'label' => 'Show Team Daily Reports',
            ],
            [
                'resource_id' => '46d89c8541c9206bc9f83f74100aa0a5ae531759',
                'name' => 'DailyReport PUT|PATCH::Update',
                'controller' => 'DailyReport',
                'action' => 'App\\Http\\Controllers\\DailyReportController@update',
                'label' => 'Update Daily Report Data',
            ],
            [
                'resource_id' => '47706256a428d261d951850a9be94537c49214bc',
                'name' => 'DailyTarget GET::Create',
                'controller' => 'DailyTarget',
                'action' => 'App\\Http\\Controllers\\DailyTargetController@create',
                'label' => 'Daily Target Create Form',
            ],
            [
                'resource_id' => 'dbd7698e12414f4b522daef8f9cde5f8038eee51',
                'name' => 'DailyTarget DELETE::Destroy',
                'controller' => 'DailyTarget',
                'action' => 'App\\Http\\Controllers\\DailyTargetController@destroy',
                'label' => 'Delete Daily Target',
            ],
            [
                'resource_id' => 'df55b8278902efde28ac1ce98704686a6554d1fb',
                'name' => 'DailyTarget GET::Edit',
                'controller' => 'DailyTarget',
                'action' => 'App\\Http\\Controllers\\DailyTargetController@edit',
                'label' => 'Daily Target Edit Form',
            ],
            [
                'resource_id' => '4cf803fb1c7b3c1a31b27549006c82c901e51e30',
                'name' => 'DailyTarget GET::Index',
                'controller' => 'DailyTarget',
                'action' => 'App\\Http\\Controllers\\DailyTargetController@index',
                'label' => 'Show Daily Targets',
            ],
            [
                'resource_id' => 'd6623108bfb1313a3b917b36657e98a3c56d04cc',
                'name' => 'DailyTarget POST::Store',
                'controller' => 'DailyTarget',
                'action' => 'App\\Http\\Controllers\\DailyTargetController@store',
                'label' => 'Store Daily Target Data',
            ],
            [
                'resource_id' => '7e489de5ca4c3bf87d94af8ea4fb3006fbd96c9e',
                'name' => 'Holiday DELETE::Destroy',
                'controller' => 'Holiday',
                'action' => 'App\\Http\\Controllers\\HolidayController@destroy',
                'label' => 'Delete Holiday',
            ],
            [
                'resource_id' => '14b286e25aa67d8d7dd63ed486d53b9b30ea4b40',
                'name' => 'Holiday GET::Edit',
                'controller' => 'Holiday',
                'action' => 'App\\Http\\Controllers\\HolidayController@edit',
                'label' => 'Holiday Edit Form',
            ],
            [
                'resource_id' => '7aa01e28a54a10e2e02bdff8088e81171a413723',
                'name' => 'Holiday POST::Import',
                'controller' => 'Holiday',
                'action' => 'App\\Http\\Controllers\\HolidayController@import',
                'label' => 'Import Holidays',
            ],
            [
                'resource_id' => '735bc9ec9210aaf516cb351225357bf4d13da4c3',
                'name' => 'Holiday GET::Index',
                'controller' => 'Holiday',
                'action' => 'App\\Http\\Controllers\\HolidayController@index',
                'label' => 'Show Holidays List',
            ],
            [
                'resource_id' => 'ee85a8babf57609a050dafffff7796d8de94e1c7',
                'name' => 'Holiday GET::Sample',
                'controller' => 'Holiday',
                'action' => 'App\\Http\\Controllers\\HolidayController@sample',
                'label' => 'Download Holiday Import Sample',
            ],
            [
                'resource_id' => '27b98fedf6d8b659817b8aa7d6baa34fea36fbcb',
                'name' => 'Holiday POST::Store',
                'controller' => 'Holiday',
                'action' => 'App\\Http\\Controllers\\HolidayController@store',
                'label' => 'Store Holiday Data',
            ],
            [
                'resource_id' => 'a493f0421aef96324e99a092b18c6a6e90b904b3',
                'name' => 'Holiday PUT|PATCH::Update',
                'controller' => 'Holiday',
                'action' => 'App\\Http\\Controllers\\HolidayController@update',
                'label' => 'Update Holiday Data',
            ],
            [
                'resource_id' => 'ead5f50133a070a8f23ee3f533416ccfd7cfe621',
                'name' => 'Project GET::Create',
                'controller' => 'Project',
                'action' => 'App\\Http\\Controllers\\ProjectController@create',
                'label' => 'Project Create Form',
            ],
            [
                'resource_id' => '385ad9fdf1c77df80c55afc6acda55e77105f712',
                'name' => 'Project DELETE::Destroy',
                'controller' => 'Project',
                'action' => 'App\\Http\\Controllers\\ProjectController@destroy',
                'label' => 'Delete Project',
            ],
            [
                'resource_id' => '5a1688d96ea698bedac6a011a59694cd3bc39664',
                'name' => 'Project GET::Edit',
                'controller' => 'Project',
                'action' => 'App\\Http\\Controllers\\ProjectController@edit',
                'label' => 'Project Edit Form',
            ],
            [
                'resource_id' => '01ab12eabaa6a508bd1a333c05b221a7d8c7f583',
                'name' => 'Project GET::Index',
                'controller' => 'Project',
                'action' => 'App\\Http\\Controllers\\ProjectController@index',
                'label' => 'Show Projects List',
            ],
            [
                'resource_id' => '19031b39e12806a7caaa244ceab8af3f9c9c3851',
                'name' => 'Project GET::Show',
                'controller' => 'Project',
                'action' => 'App\\Http\\Controllers\\ProjectController@show',
                'label' => 'View Single Project',
            ],
            [
                'resource_id' => '5a54ad44ed190e7b22bec615c1a236dc7124b157',
                'name' => 'Project POST::Store',
                'controller' => 'Project',
                'action' => 'App\\Http\\Controllers\\ProjectController@store',
                'label' => 'Store Project Data',
            ],
            [
                'resource_id' => 'eac22b896ff69274e6f3921f3c2b452bf0a9f66a',
                'name' => 'Project PUT|PATCH::Update',
                'controller' => 'Project',
                'action' => 'App\\Http\\Controllers\\ProjectController@update',
                'label' => 'Update Project Data',
            ],
            [
                'resource_id' => 'a5c8c5bc0e9e8be28dc8484e893d502e6105032e',
                'name' => 'SocialPlatform GET::Create',
                'controller' => 'SocialPlatform',
                'action' => 'App\\Http\\Controllers\\SocialPlatformController@create',
                'label' => 'Social Platform Create Form',
            ],
            [
                'resource_id' => '09d7552c203885de5c29a66a1dc397fda60a43a1',
                'name' => 'SocialPlatform DELETE::Destroy',
                'controller' => 'SocialPlatform',
                'action' => 'App\\Http\\Controllers\\SocialPlatformController@destroy',
                'label' => 'Delete Social Platform',
            ],
            [
                'resource_id' => 'ca10115cb9dd077d663f9bd43a899518a159d0fd',
                'name' => 'SocialPlatform GET::Edit',
                'controller' => 'SocialPlatform',
                'action' => 'App\\Http\\Controllers\\SocialPlatformController@edit',
                'label' => 'Social Platform Edit Form',
            ],
            [
                'resource_id' => '8c1d7546973fdd8dc56d37b28e74488444d80a5c',
                'name' => 'SocialPlatform GET::Index',
                'controller' => 'SocialPlatform',
                'action' => 'App\\Http\\Controllers\\SocialPlatformController@index',
                'label' => 'Show Social Platforms List',
            ],
            [
                'resource_id' => 'cb9f18fe09e5ec06af30c6ca6a35ab5b54d3c457',
                'name' => 'SocialPlatform GET::Show',
                'controller' => 'SocialPlatform',
                'action' => 'App\\Http\\Controllers\\SocialPlatformController@show',
                'label' => 'View Single Social Platform',
            ],
            [
                'resource_id' => '0578c6049953c254f08bd22372c3fcbcb7158119',
                'name' => 'SocialPlatform POST::Store',
                'controller' => 'SocialPlatform',
                'action' => 'App\\Http\\Controllers\\SocialPlatformController@store',
                'label' => 'Store Social Platform Data',
            ],
            [
                'resource_id' => 'ed7a11865459fa653749e2c3af9be22b711c1164',
                'name' => 'SocialPlatform PUT|PATCH::Update',
                'controller' => 'SocialPlatform',
                'action' => 'App\\Http\\Controllers\\SocialPlatformController@update',
                'label' => 'Update Social Platform Data',
            ],
        ];

        foreach ($rows as &$row) {
            $row['created_at'] = $timestamp;
            $row['updated_at'] = $timestamp;
        }
        unset($row);

        foreach (array_chunk($rows, 200) as $chunk) {
            Resource::query()->upsert(
                $chunk,
                ['resource_id'],
                ['name', 'controller', 'action', 'label', 'updated_at']
            );
        }
    }
}

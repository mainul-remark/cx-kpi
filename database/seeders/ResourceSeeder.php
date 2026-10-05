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
                'resource_id' => '25872440b8776f44c66023ad19eca3cafa20a4a6',
                'name' => 'Backend-SiteSettings GET::Create',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@create',
                'label' => null,
            ],
            [
                'resource_id' => 'dddbe3dc8805969239c36d790bf59860654e8642',
                'name' => 'Backend-SiteSettings DELETE::Destroy',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@destroy',
                'label' => null,
            ],
            [
                'resource_id' => 'd70f47e60dbe156dd1d6927c440eda47fdb799b1',
                'name' => 'Backend-SiteSettings GET::Edit',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@edit',
                'label' => null,
            ],
            [
                'resource_id' => 'b1235011231a66e26104c88994213c6c6b0df364',
                'name' => 'Backend-SiteSettings GET::Index',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@index',
                'label' => null,
            ],
            [
                'resource_id' => 'ff0129c8f9ec1ef51a167039952360f6c12ac4ea',
                'name' => 'Backend-SiteSettings POST::SaveTheme',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@saveTheme',
                'label' => null,
            ],
            [
                'resource_id' => '65b9d33ea34c220a68fb9e3fae5565b4fe293d5b',
                'name' => 'Backend-SiteSettings GET::Show',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@show',
                'label' => null,
            ],
            [
                'resource_id' => 'cd546985b26dac182633cc7fcb36e328954c7b75',
                'name' => 'Backend-SiteSettings POST::Store',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@store',
                'label' => null,
            ],
            [
                'resource_id' => '3dab20db45c360431502fa623f50ad6a128b0f49',
                'name' => 'Backend-SiteSettings PUT|PATCH::Update',
                'controller' => 'Backend-SiteSettings',
                'action' => 'App\\Http\\Controllers\\Backend\\SiteSettingsController@update',
                'label' => null,
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

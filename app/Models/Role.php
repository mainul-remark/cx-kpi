<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Uzzal\Acl\Models\Permission;

class Role extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'role_id';
    protected $guarded = [];

    public function getPermissions(){
        return $this->hasMany(Permission::class,'role_id','role_id');
    }

    public function users() {
        return $this->belongsToMany(User::class, 'user_roles', 'user_id', 'role_id');
    }

    public function userStoreAssignments(): HasMany
    {
        return $this->hasMany(UserStoreAssignment::class, 'role_id', 'role_id');
    }

    public static function checkValidation($request)
    {
        return Validator::make($request->all(), [
            'name'          => 'required|max:30|unique:roles',
            'resource'      => 'required|array',
            'resource.*'    => 'min:1',
        ],
            [
                'name.required'     => 'Role name field is required',
                'name.max'          => 'Role name maximum length is 30',
                'name.unique'       => 'Role names must be unique',
                'resource.required' => 'Select a user role for access permission.',
                'resource.min'      => 'Minimum  single permission is required.',
            ]
        );
    }

    public static function updateValidation($request, $role)
    {
        return Validator::make(
            $request->all(),
            [
                'name'       => 'required|string|max:30|unique:roles,name,' . $role->role_id . ',role_id',
                'resource'   => 'required|array|min:1',
                'resource.*' => 'exists:resources,resource_id',
            ],
            [
                'name.required'     => 'Role name field is required.',
                'name.max'          => 'Role name maximum length is 30.',
                'name.unique'       => 'Role names must be unique.',

                'resource.required' => 'Select at least one permission for this role.',
                'resource.array'    => 'Invalid permission data format.',
                'resource.min'      => 'Minimum single permission is required.',
                'resource.*.exists' => 'Invalid permission selected.',
            ]
        );
    }



    public static function storeRoleInfo($request){

        $role =  Role::create([
            'name'  =>  Str::slug($request->name)??''
        ]);

        if(!empty($role)) {
            self::storePermissionInfo($request,$role);
        }
        return $role;
    }

    public static function updateRoleInfo($request,$role){

        $data = Role::where('role_id',$role->role_id)->update([
            'name'  =>  Str::slug($request->name)??''
        ]);

        $existPermissions = Permission::where('role_id',$role->role_id)->get()??'';

        if(!empty($existPermissions)){
            foreach ($existPermissions as $existPermission){
                Permission::where('resource_id',$existPermission->resource_id)
                    ->where('role_id',$role->role_id)
                    ->delete();
            }
            if(!empty($role)){
                Role::storePermissionInfo($request,$role);
            }
        }
        return $data;
    }


    public static  function storePermissionInfo($request,$role){
        if(!empty($request->resource)){
            foreach ($request->resource as $item){
                Permission::create([
                    'role_id'     => $role->role_id,
                    'resource_id' =>  $item,
                ]);
            }
        }
    }
}

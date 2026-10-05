<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\Request;
use Exception;
use Uzzal\Acl\Models\Permission;
use Uzzal\Acl\Models\Resource;

class RoleController extends Controller
{
    public function index()
    {
        try {
            $rows = Role::whereNotIn('role_id',[1])->latest()->get()??'';
            return view('backend.user-management.roles.index',compact('rows'));
        }catch (Exception $ex){
            echo 'Caught exception: ', $ex->getMessage(), "\n";
        }
    }

    public function create()
    {
        $resources = Resource::whereNotIn('controller',['Role','Resource'])->get()??'';

        $data = [];
        foreach ($resources as $key=>$resource){
            $data[$resource->controller][] = [
                'id'    =>  $resource->resource_id??'',
                'name'  =>  $resource->name??'',
            ];
        }
        return view('backend.user-management.roles.create',[
            'resources' =>  $resources??'',
            'data'      =>  $data??'',
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validator = Role::checkValidation($request);
            if($validator->fails()){
                return back()->withErrors($validator)->withInput();
            }

            $save_data =  Role::storeRoleInfo($request);

            if($save_data){
                return redirect('admin/roles')->with(['message'=> 'Role created successfully !','alert-type'=>'primary']);
            }

        }catch (Exception $ex){
            echo 'Caught exception: ', $ex->getMessage(), "\n";
        }
    }

    public function show(string $id)
    {
        //
    }

    public function edit(Role $role)
    {
        $resources   = Resource::whereNotIn('controller', ['Role','Resource'])->get();
        $permissions = Permission::where('role_id', $role->role_id)->get();

        $data = [];
        foreach ($resources as $resource){
            $data[$resource->controller][] = [
                'id'    => $resource->resource_id,
                'name'  => $resource->name,
                'label'  => $resource->label,
            ];
        }

        $permissionIds = $permissions->pluck('resource_id')->toArray();

        return view('backend.user-management.roles.edit', [
            'role'          => $role,
            'data'          => $data,
            'resources'     => $resources,
            'permissions'   => $permissions,
            'permissionIds' => $permissionIds,
        ]);
    }

    public function update(Request $request, Role $role)
    {
        try {
            $validator = Role::updateValidation($request,$role);
            if($validator->fails()){
                return back()->withErrors($validator)->withInput();
            }

            $update_data =  Role::updateRoleInfo($request,$role);

            if($update_data){
                return redirect('/admin/roles')->with(['message'=> 'Role updated successfully !!','alert-type'=>'primary']);
            }
        }catch (Exception $ex){
            echo 'Caught exception: ', $ex->getMessage(), "\n";
        }
    }

    public function destroy(Role $role)
    {
        if(!empty($role)){
            if(hasRole(['super-admin'])){
                $permission =  Permission::where('role_id',$role->role_id)->delete()??'';
                if(!empty($permission)){
                    $role->delete();
                }
                return redirect('/admin/roles')->with(['message'=>'Role deleted successfully !!','alert-type'=>'error']);
            }else{
                return redirect('/admin/roles')->with(['message'=>'This action is unauthorized ??','alert-type'=>'error']);
            }
        }else{
            return redirect('/admin/roles')->with(['message'=> 'Role Not Found !','alert-type'=>'error']);
        }
    }
}

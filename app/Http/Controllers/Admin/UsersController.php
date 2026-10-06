<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserImportRequest;
use App\Imports\User\UsersImport;
use App\Models\Role;
use App\Models\Store;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Exception;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use Yajra\DataTables\DataTables;

class UsersController extends Controller
{
    public function index(Request $request){
        if (!$request->ajax()) {
            return view('backend.user-management.users.index', [
                'roles' => Role::query()
                    ->where('role_id', '!=', 1)
                    ->orderBy('name')
                    ->get(['role_id', 'name']),
            ]);
        }
        try {
            $users = User::with([
                    'roles:role_id,name',
                ])
                ->select('id','name','email','employee_id','profile_image','created_at','is_active')
                ->when($request->input('role_id'), function ($query, $roleId) {
                    $query->whereHas('roles', fn ($q) => $q->where('roles.role_id', $roleId));
                })
                ->latest()
                ->get();


            $payload = [
                'data' => $users->map(function ($user) {

                    $roleNames = $user->roles->pluck('name')->filter()->values();

                    return [
                        'id'                    => $user->id,
                        'name'                  => $user->name,
                        'email'                 => $user->email,
                        'employee_id'           => $user->employee_id,
//                        'mobile_no'     => $user->mobile_no,
//                        'account_type'  => $user->account_type,
                        'profile_image'         => (!empty($user->profile_image) && file_exists(public_path($user->profile_image)))
                            ? asset($user->profile_image)
                            : asset('backend/remark-logo.png'),
                        'roles'                 => $roleNames,
                        'role_names'            => $roleNames->implode(', '),
                        'created_at'            => optional($user->created_at)->format('Y-m-d'),
                        'is_active'             => $user->is_active,
                    ];
                }),
            ];

            return response()->json($payload);

        }catch (Exception $e){

            return response()->json([
                'data'    => [],
                'success' => false,
                'message' => 'Failed to load users data. Please try again.',
            ], 500);
        }


    }
    public function create()
    {
        $roles = Role::whereNotIn('role_id',[1])->get(['role_id','name'])??'';
        return view('backend.user-management.users.create',['roles'=>$roles,]);
    }

    public function import(UserImportRequest $request): JsonResponse
    {
        $import = new UsersImport();
        $uploadedFile = $request->file('file');

        Excel::import($import, $uploadedFile);

        if ($import->hasFailures()) {
            $failures = $import->getFailures();

            activity('system')
                ->causedBy(auth()->user())
                ->event('user_import_failed')
                ->withProperties([
                    'file_name' => $uploadedFile?->getClientOriginalName(),
                    'imported_count' => $import->getImportedCount(),
                    'error_count' => count($failures),
                ])
                ->log('User import failed.');

            return response()->json([
                'success'        => false,
                'message'        => 'Import failed. Please fix the listed rows and re-upload.',
                'imported_count' => $import->getImportedCount(),
                'error_count'    => count($failures),
                'errors'         => $failures,
            ], 422);
        }

        activity('system')
            ->causedBy(auth()->user())
            ->event('user_import_completed')
            ->withProperties([
                'file_name' => $uploadedFile?->getClientOriginalName(),
                'imported_count' => $import->getImportedCount(),
                'error_count' => 0,
            ])
            ->log('User import completed successfully.');

        return response()->json([
            'success'        => true,
            'message'        => "Import successful. {$import->getImportedCount()} user(s) imported.",
            'imported_count' => $import->getImportedCount(),
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validator = User::checkValidation($request);
            if ($validator->fails()) {
                return redirect()->back()->withErrors($validator)->withInput();
            }
            $user =  User::storeUserInfo($request);
            if($user){
                return back()->with(['message' => 'User created successfully','alert-type' => 'primary']);
            }
        }catch (Exception $ex){
            return redirect()->back()->with('error',$ex->getMessage());
        }
    }

    public function show(User $user)
    {
        return view('backend.user-management.users.show',['user'=>$user]);
    }

    public function edit(User $user)
    {
        $roles = Role::whereNotIn('role_id',[1])->get(['role_id','name'])??'';
        return view('backend.user-management.users.edit',['user'=>$user,'roles' => $roles,]);
    }

    public function update(Request $request, User $user)
    {
        try {
            $validator = User::updateValidation($request,$user);
            if($validator->fails()){
                return back()->withErrors($validator)->withInput();
            }

            $update_data =  User::updateUserInfo($request,$user);

            if($update_data){
                return redirect('admin/users')->with(['message'=> 'User updated successfully !','alert-type'=>'primary']);
            }
        }catch (Exception $ex){
            echo 'Caught exception: ', $ex->getMessage(), "\n";
        }
    }

    public function destroy(User $user)
    {
        // 1️⃣ Permission check আগে
        if (!hasRole(['super-admin'])) {
            return response()->json([
                'status'  => false,
                'message' => 'This action is unauthorized!',
            ], 403);
        }

        // 2️⃣ চাইলে নিজের account delete ব্লক করতে পারো (recommended)
        if ($user->id === auth()->id()) {
            return response()->json([
                'status'  => false,
                'message' => 'You cannot delete your own account.',
            ], 422);
        }

//        $user->update(['is_active' => 0]);
//        if ($user->account_type === 'backend') {
//            UserRole::where('user_id', $user->id)->delete();
//        }
        if ($user->profile_image && file_exists(public_path($user->profile_image))) {
            @unlink(public_path($user->profile_image));
        }
        $user->delete();
        return response()->json([
            'status'  => true,
            'message' => 'User deleted successfully!',
        ]);
    }


    public function profile(){
        $user = Auth::user();
        if(!empty($user)){
            return view('backend.user-management.users.profile',['user' => $user??'']);
        }else{
            Auth::logout();
            return redirect()->route('login');
        }
    }
    public function updateProfile(Request $request){

        $user = Auth::user();

        if(!empty($user)){
            $this->validate($request,[
//                'mobile_no'     => 'required|regex:/^(01)[0-9]{9}$/|max:11|unique:users,mobile_no,' . $user->id,
                'profile_image' => 'nullable|image|mimes:jpeg,jpg,webp|max:1024',
            ]);

            $update_data = User::profileInfoUpdate($request,$user);

            if($update_data){
                return redirect()->back()->with(['message'=>'Profile info updated !!','alert-type'=>'primary']);
            }else{
                return redirect()->back()->with(['message'=>'Profile info not updated !!','alert-type'=>'error']);
            }
        }else{
            Auth::logout();
            return redirect()->route('login');
        }
    }
    public function pwChange()
    {
        return view('backend.user-management.users.password');
    }
    public function pwUpdate(Request $request){

        $this->validate($request,[
            'old_password'          =>  'required',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#$]).+$/'
            ],
            'password_confirmation' =>  'required',
        ]);

        if(!Hash::check($request->old_password, auth()->user()->password)){
            return back()->with(['message'=>'Old password not matched .','alert-type'=>'error']);
        }else{
            User::where('id', auth()->user()->id )->update([
                'password' => Hash::make($request->password),
                'password_changed_at'=> now(),
            ]);
            Auth::logout();
            return redirect('/login')->with(['message'=>'Password change successfully !!','alert-type'=>'primary']);
        }
    }
}

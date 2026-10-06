<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Intervention\Image\Laravel\Facades\Image;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Mainul\CustomHelperFunctions\Helpers\CustomHelper;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Uzzal\Acl\Traits\AccessControlled;

class User extends Authenticatable
{
    use HasApiTokens, AccessControlled;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;
    use LogsActivity;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'profile_image',
        'usages_sector',
        'employee_id',
    ];

//    protected $guarded = [];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('data')
            ->logOnly([
                'name',
                'email',
                'profile_image',
                'usages_sector',
                'employee_id',
                'password_changed_at',
                'email_verified_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }


//    user code from valex crud by reza vai

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id');
    }
    public function getUserRoles(){
        return $this->hasMany(UserRole::class, 'user_id','id');
    }

    public function dailyReports(): HasMany
    {
        return $this->hasMany(DailyReport::class);
    }

    public static function checkValidation($request)
    {
        return Validator::make($request->all(), [

            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:users,email',
            'password' => [
                'required',
                'string',
                'min:8',
                'confirmed',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#$]).+$/'
            ],
//                'mobile_no'     => ['required','regex:/^(01)[0-9]{9}$/', 'unique:users,mobile_no'],
            'role_id'       => 'required|array',
            'role_id.*'     => 'integer',
            'employee_id'   => 'required|string',
            'usages_sector' => 'required|in:field,corporate',
            'assign_all_stores' => 'nullable|boolean',
            'store_ids'     => [Rule::requiredIf(fn () => $request->input('usages_sector') === 'field' && !$request->boolean('assign_all_stores')), 'array'],
            'store_ids.*'   => ['integer', Rule::exists('stores', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where('status', 1))],
            'profile_image' => 'nullable|image|mimes:jpeg,jpg,webp,png|max:1048',

        ], [
            'name.required'         => 'Name is required.',
            'email.required'        => 'Email is required.',
            'email.email'           => 'Provide valid email address.',
            'email.unique'          => 'Email already exists.',
            'employee_id.required'  => 'Employee ID is required.',
            'password.required'     => 'Password field is required.',
            'password.min'          => 'Password must be at least 8 characters long.',
            'password.confirmed'    => 'Password confirmation does not match.',
            'password.regex'        => 'Password must contain uppercase, lowercase, number and a special character (@, #, $).',
//                'mobile_no.required'    => 'Mobile number is required.',
//                'mobile_no.regex'       => 'Mobile number must be 11 digits & start with 01.',
//                'mobile_no.unique'      => 'Mobile number already exists.',
            'role_id.required'      => 'Select at least one role.',
            'usages_sector.required'=> 'Select at least one Sector among Corporate and Field.',
            'store_ids.required'    => 'Please assign at least one store for a field user, or check "Assign all stores".',
            'store_ids.*.exists'    => 'One or more selected stores are invalid or inactive.',
            'profile_image.image'   => 'Please upload valid image file.',
            'profile_image.mimes'   => 'Image must be jpeg, jpg, png or webp format.',
            'profile_image.max'     => 'Image size must be less than 1MB.',

        ]);
    }

    public static function updateValidation($request,$user)
    {
        return Validator::make($request->all(), [
//            'account_type'  => 'required|in:frontend,backend',
            'name'          => 'required|string|max:255',
            'email'         => 'required|email|max:255|unique:users,email,'. $user->id,
            'password' => [
                'nullable',
                'string',
                'min:8',
                'regex:/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@#$]).+$/'
            ],

//            'mobile_no'     => ['required','regex:/^(01)[0-9]{9}$/', 'unique:users,mobile_no,' . $user->id],
            'role_id'       => 'required|array',
            'role_id.*'     => 'integer',
            'employee_id'   => 'required|string',
            'usages_sector' => 'required|in:field,corporate',
            'assign_all_stores' => 'nullable|boolean',
            'store_ids'     => [Rule::requiredIf(fn () => $request->input('usages_sector') === 'field' && !$request->boolean('assign_all_stores')), 'array'],
            'store_ids.*'   => ['integer', Rule::exists('stores', 'id')->where(fn ($q) => $q->whereNull('deleted_at')->where('status', 1))],
            'profile_image' => 'nullable|image|mimes:jpeg,jpg,webp,png|max:1048',

        ], [

//            'account_type.required' => 'Account type field is required.',
//            'account_type.in'       => 'Account type must be frontend or backend.',
            'name.required'         => 'Name is required.',
            'email.required'        => 'Email is required.',
            'email.email'           => 'Provide valid email address.',
            'email.unique'          => 'Email already exists.',
            'password.min'          => 'Password must be at least 8 characters long.',
            'password.regex'        => 'Password must contain uppercase, lowercase, number and a special character (@, #, $).',
//            'mobile_no.required'    => 'Mobile number is required.',
//            'mobile_no.regex'       => 'Mobile number must be 11 digits & start with 01.',
//            'mobile_no.unique'      => 'Mobile number already exists.',
            'role_id.required'      => 'Select at least one role.',
            'employee_id.required'  => 'Employee ID is required.',
            'store_ids.required'    => 'Please assign at least one store for a field user, or check "Assign all stores".',
            'store_ids.*.exists'    => 'One or more selected stores are invalid or inactive.',
            'profile_image.image'   => 'Please upload valid image file.',
            'profile_image.mimes'   => 'Image must be jpeg, jpg, png or webp format.',
            'profile_image.max'     => 'Image size must be less than 1MB.',

        ]);
    }
    public static function imageUpload($request)
    {
        if ($request->hasFile('profile_image')) {
            $file = $request->file('profile_image');
            $ext = uniqid() . '.' . $file->getClientOriginalExtension();
            if ($request->is('admin/*')) {
                $store = './backend/uploads/';
            } else {
                $store = './front/uploads/';
            }
            $path = $store . $ext;
            Image::read($file)->resize(160, 135)->save($path, quality: 75, progressive: true);
            return $path;
        }
    }

    public static function storeUserInfo($request)
    {
        $userData = [
            'name'                  => $request->name ?? null,
            'email'                 => $request->email ?? null,
            'password'              => Hash::make($request->password),
//            'mobile_no'             => $request->mobile_no,
//            'account_type'          => $request->account_type,
            'usages_sector'         => $request->usages_sector ?? 'field',
            'employee_id'           => $request->employee_id   ?? '',
            'password_changed_at'   => now(),
            'profile_image'         => CustomHelper::fileUpload($request->file('profile_image'), 'profile-image','profile-image', 200,160),
        ];

        $user = User::create($userData);

        if ($user && !empty($request->role_id)) {
            self::insertUserRoleInfo($request, $user);
        }

        if ($user) {
            self::syncStoreAssignments($request, $user);
        }

        return $user;
    }

    public static function updateUserInfo($request, $user)
    {
        $userData = [
            'name'          => $request->name ?? $user->name,
            'email'         => $request->email ?? null,
            'usages_sector' => $request->usages_sector ?? 'field',
            'employee_id'   => $request->employee_id ?? '',
//            'mobile_no'     => $request->mobile_no,
//            'account_type'  => $request->account_type,
//            'is_active'     => $request->is_active,
        ];

        if ($request->filled('password')) {
            $userData['password']            = Hash::make($request->password);
            $userData['password_changed_at'] = now();
        }

//        if ($request->hasFile('profile_image')) {
//            $image = $request->file('profile_image');
//
//            if (!empty($image)) {
//                if (!empty($user->profile_image) && file_exists($user->profile_image)) {
//                    @unlink($user->profile_image);
//                }
//
//                $userData['profile_image'] = CustomHelper::fileUpload($request->file('profile_image'), 'profile-image','profile-image', 200,160);
//            }
//        } else {
//            $userData['profile_image'] = $user->profile_image;
//        }
        $userData['profile_image'] = CustomHelper::fileUpload($request->file('profile_image'), 'profile-image','profile-image', 200,160, $user->profile_image);
        $user->update($userData);

            UserRole::where('user_id', $user->id)->delete();
            if (!empty($request->role_id)) {
                self::insertUserRoleInfo($request, $user);
            }


        return $user;
    }

    public static function profileInfoUpdate($request, $user)
    {
//        $image = $request->hasFile('profile_image');
//        if (!empty($image)) {
//            if (file_exists($user->profile_image)) {
//                unlink($user->profile_image);
//                $imageUrl = self::imageUpload($request);
//            } else {
//                $imageUrl = self::imageUpload($request);
//            }
//
//        } else {
//            $imageUrl = $user->profile_image;
//        }

        $user->update([
            'name'              => $request->name ?? $user->name,
            'email'             => $request->email ?? $user->email,
//            'mobile_no'         => $request->mobile_no ?? $user->mobile_no,
            'profile_image'     => CustomHelper::fileUpload($request->file('profile_image'), 'profile-image','profile-image', 200,160, $user->profile_image),
        ]);
        return $user;
    }

    public static function insertUserRoleInfo($request, $user)
    {
        if (!empty($request->role_id)) {
            foreach ($request->role_id as $item) {
                UserRole::create([
                    'user_id' => $user->id ?? 0,
                    'role_id' => $item ?? 0
                ]);
            }
        }
    }

//    check if super admin
    public function isAdmin(): bool
    {
        return $this->roles()->whereIn('name', ['super-admin', 'system-admin'])->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->roles()->where('name', 'super-admin')->exists();
    }

    public function isSystemAdmin(): bool
    {
        return $this->roles()->where('name', 'system-admin')->exists();
    }

}

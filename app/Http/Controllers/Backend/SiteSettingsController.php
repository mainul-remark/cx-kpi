<?php

namespace App\Http\Controllers\Backend;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class SiteSettingsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return view('backend.common-pages.site-config.site-settings', ['siteSetting' => SiteSetting::first()]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $this->validateData($request);

        $siteSetting = SiteSetting::first();
        $validated = $this->handleUploads($request, $validated, $siteSetting);

        if ($siteSetting) {
            $siteSetting->update($validated);
            return response()->json(['success' => true, 'message' => 'Site settings updated successfully.']);
        }

        SiteSetting::create($validated);
        return response()->json(['success' => true, 'message' => 'Site settings saved successfully.']);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $siteSetting = SiteSetting::findOrFail($id);
        return response()->json($siteSetting);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $siteSetting = SiteSetting::findOrFail($id);
        return response()->json($siteSetting);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $siteSetting = SiteSetting::findOrFail($id);
        $validated = $this->validateData($request);
        $validated = $this->handleUploads($request, $validated, $siteSetting);

        $siteSetting->update($validated);

        return response()->json(['success' => true, 'message' => 'Site settings updated successfully.']);
    }

    public function saveTheme(Request $request)
    {
        $validated = $this->validateThemeData($request);

        $siteSetting = SiteSetting::first();
        if ($siteSetting) {
            $siteSetting->update($validated);
        } else {
            $siteSetting = SiteSetting::create($validated);
        }

        return response()->json([
            'success' => true,
            'message' => 'Theme settings saved successfully.',
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $siteSetting = SiteSetting::findOrFail($id);
        $this->deleteFiles($siteSetting);
        $siteSetting->delete();

        return response()->json(['success' => true, 'message' => 'Site settings deleted successfully.']);
    }

    private function validateData(Request $request): array
    {
        if ($request->filled('office_mobile')) {
            $request->merge([
                'office_mobile' => preg_replace('/[\s-]+/', '', (string) $request->input('office_mobile')),
            ]);
        }

        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'meta_title' => ['nullable', 'string', 'max:2000'],
            'meta_description' => ['nullable', 'string', 'max:3000'],
            'favicon' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg,ico', 'max:4096'],
            'menu_logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:4096'],
            'meta_header' => ['nullable', 'string'],
            'meta_footer' => ['nullable', 'string'],
            'site_info' => ['nullable', 'string'],
            'header_custom_code' => ['nullable', 'string'],
            'footer_custom_code' => ['nullable', 'string'],
            'office_mobile' => ['nullable', 'string', 'max:14', 'regex:/^(?:\+?880|0)1[3-9]\d{8}$/'],
            'office_email' => ['nullable', 'email', 'max:255'],
            'office_address' => ['nullable', 'string', 'max:255'],
            'banner' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,svg', 'max:8192'],
        ], $this->validationMessages());
    }

    private function validateThemeData(Request $request): array
    {
        foreach (['menu_color_code', 'header_color_code', 'theme_primary_code', 'theme_bg_color_code'] as $rgbField) {
            if ($request->filled($rgbField)) {
                $request->merge([$rgbField => $this->normalizeRgb((string) $request->input($rgbField))]);
            }
        }

        return $request->validate([
            'theme_style' => ['nullable', 'in:light,dark'],
            'direction' => ['nullable', 'in:ltr,rtl'],
            'navigation_style' => ['nullable', 'in:horizontal,vertical'],
            'navigation_menu_styles' => ['nullable', 'in:menu-click,menu-hover,icon-click,icon-hover,default,closed,icontext,overlay,detached,doublemenu'],
            'page_styles' => ['nullable', 'in:regular,classic,modern'],
            'layout_width' => ['nullable', 'in:fullwidth,boxed'],
            'menu_positions' => ['nullable', 'in:fixed,scrollable'],
            'header_positions' => ['nullable', 'in:fixed,scrollable'],
            'page_loader' => ['nullable', 'in:enable,disable'],
            'menu_colors' => ['nullable', 'in:light,dark,color,gradient,transparent'],
            'menu_color_code' => ['nullable', 'string', 'max:20', 'regex:/^(25[0-5]|2[0-4]\d|1?\d?\d),(25[0-5]|2[0-4]\d|1?\d?\d),(25[0-5]|2[0-4]\d|1?\d?\d)$/'],
            'header_colors' => ['nullable', 'in:light,dark,color,gradient,transparent'],
            'header_color_code' => ['nullable', 'string', 'max:20', 'regex:/^(25[0-5]|2[0-4]\d|1?\d?\d),(25[0-5]|2[0-4]\d|1?\d?\d),(25[0-5]|2[0-4]\d|1?\d?\d)$/'],
            'theme_primary' => ['nullable', 'string', 'max:50'],
            'theme_primary_code' => ['nullable', 'string', 'max:20', 'regex:/^(25[0-5]|2[0-4]\d|1?\d?\d),(25[0-5]|2[0-4]\d|1?\d?\d),(25[0-5]|2[0-4]\d|1?\d?\d)$/'],
            'theme_bg_color' => ['nullable', 'string', 'max:50'],
            'theme_bg_color_code' => ['nullable', 'string', 'max:20', 'regex:/^(25[0-5]|2[0-4]\d|1?\d?\d),(25[0-5]|2[0-4]\d|1?\d?\d),(25[0-5]|2[0-4]\d|1?\d?\d)$/'],
            'menu_bg_img' => ['nullable', 'in:bgimg1,bgimg2,bgimg3,bgimg4,bgimg5'],
        ]);
    }

    private function normalizeRgb(string $value): string
    {
        return preg_replace('/\s+/', '', trim($value));
    }

    private function validationMessages(): array
    {
        return [
            'title.required' => 'The site title is required.',
            'title.max' => 'The site title cannot exceed 255 characters.',
            'meta_title.max' => 'The meta title cannot exceed 2000 characters.',
            'meta_description.max' => 'The meta description cannot exceed 3000 characters.',
            'favicon.mimes' => 'The favicon must be a valid image (jpg, jpeg, png, webp, svg, ico).',
            'menu_logo.mimes' => 'The menu logo must be a valid image (jpg, jpeg, png, webp, svg).',
            'logo.mimes' => 'The logo must be a valid image (jpg, jpeg, png, webp, svg).',
            'banner.mimes' => 'The banner must be a valid image (jpg, jpeg, png, webp, svg).',
            'favicon.max' => 'The favicon must not be larger than 4MB.',
            'menu_logo.max' => 'The menu logo must not be larger than 4MB.',
            'logo.max' => 'The logo must not be larger than 4MB.',
            'banner.max' => 'The banner must not be larger than 8MB.',
            'office_email.email' => 'Please enter a valid office email address.',
            'office_email.max' => 'The office email cannot exceed 255 characters.',
            'office_mobile.max' => 'The office mobile number cannot exceed 14 characters.',
            'office_mobile.regex' => 'Please enter a valid Bangladeshi mobile number (e.g., 01712345678 or +8801712345678).',
            'office_address.max' => 'The office address cannot exceed 255 characters.',
        ];
    }

    private function handleUploads(Request $request, array $validated, ?SiteSetting $siteSetting): array
    {
        $fields = [
            'favicon' => 'favicon',
            'menu_logo' => 'menu-logo',
            'logo' => 'logo',
            'banner' => 'banner',
        ];

        foreach ($fields as $field => $prefix) {
            if (!$request->hasFile($field)) {
                continue;
            }

            if ($siteSetting && $siteSetting->{$field} && File::exists(public_path($siteSetting->{$field}))) {
                File::delete(public_path($siteSetting->{$field}));
            }

            $validated[$field] = $this->storeFile($request->file($field), $prefix);
        }

        return $validated;
    }

    private function storeFile($file, string $prefix): string
    {
        $dir = public_path('uploads/site-settings');
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $name = $prefix . '-' . time() . '-' . uniqid() . '.' . $file->getClientOriginalExtension();
        $file->move($dir, $name);

        return 'uploads/site-settings/' . $name;
    }

    private function deleteFiles(SiteSetting $siteSetting): void
    {
        $fields = ['favicon', 'menu_logo', 'logo', 'banner'];
        foreach ($fields as $field) {
            if ($siteSetting->{$field} && File::exists(public_path($siteSetting->{$field}))) {
                File::delete(public_path($siteSetting->{$field}));
            }
        }
    }
}

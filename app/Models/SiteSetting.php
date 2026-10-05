<?php

namespace App\Models;

use App\Models\Scopes\Searchable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SiteSetting extends Model
{
    use HasFactory;
    use Searchable;
    use SoftDeletes;

    protected $fillable = [
        'title',
        'meta_title',
        'meta_description',
        'favicon',
        'menu_logo',
        'logo',
        'meta_header',
        'site_color',
        'meta_footer',
        'site_info',
        'header_custom_code',
        'footer_custom_code',
        'office_mobile',
        'office_email',
        'office_address',
        'banner',

//        valex theme settings columns
        'theme_style',
        'direction',
        'navigation_style',
        'navigation_menu_styles',
        'page_styles',
        'layout_width',
        'menu_positions',
        'header_positions',
        'page_loader',
        'menu_colors',
        'menu_color_code',
        'header_colors',
        'header_color_code',
        'theme_primary',
        'theme_primary_code',
        'theme_bg_color',
        'theme_bg_color_code',
        'menu_bg_img',
    ];

    protected $searchableFields = ['*'];

    protected $table = 'site_settings';
}

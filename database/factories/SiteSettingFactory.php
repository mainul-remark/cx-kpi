<?php

namespace Database\Factories;

use App\Models\SiteSetting;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Factories\Factory;

class SiteSettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SiteSetting::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(10),
            'meta_title' => $this->faker->text(),
            'meta_description' => $this->faker->text(),
            'favicon' => $this->faker->text(255),
            'menu_logo' => $this->faker->text(255),
            'logo' => $this->faker->text(255),
            'meta_header' => $this->faker->text(),
            'site_color' => $this->faker->text(255),
            'meta_footer' => $this->faker->text(),
            'site_info' => $this->faker->text(),
            'header_custom_code' => $this->faker->text(),
            'footer_custom_code' => $this->faker->text(),
            'office_mobile' => $this->faker->phoneNumber(),
            'office_email' => $this->faker->email(),
            'office_address' => $this->faker->address(),
            'banner' => $this->faker->text(),
        ];
    }
}

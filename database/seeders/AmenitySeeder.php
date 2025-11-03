<?php

namespace Database\Seeders;

use App\Models\Amenity;
use Illuminate\Database\Seeder;

class AmenitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $amenities = [
            [
                'name' => 'ac',
                'display_name' => 'Air conditioning',
                'icon_url' => 'Wind', // lucide-react icon name
            ],
            [
                'name' => 'bathtub',
                'display_name' => 'Bathtub',
                'icon_url' => 'Bath',
            ],
            [
                'name' => 'bed_linens',
                'display_name' => 'Bed linens',
                'icon_url' => 'BedDouble',
            ],
            [
                'name' => 'carbon_monoxide_detector',
                'display_name' => 'Carbon monoxide detector',
                'icon_url' => 'ShieldAlert',
            ],
            [
                'name' => 'coffee_maker',
                'display_name' => 'Coffee maker',
                'icon_url' => 'Coffee',
            ],
            [
                'name' => 'cooking_basics',
                'display_name' => 'Cooking basics',
                'icon_url' => 'ChefHat',
            ],
            [
                'name' => 'dishes_and_silverware',
                'display_name' => 'Dishes and silverware',
                'icon_url' => 'UtensilsCrossed',
            ],
            [
                'name' => 'dishwasher',
                'display_name' => 'Dishwasher',
                'icon_url' => 'Sparkles',
            ],
            [
                'name' => 'dryer',
                'display_name' => 'Dryer',
                'icon_url' => 'Droplets',
            ],
            [
                'name' => 'free_parking',
                'display_name' => 'Free parking on premises',
                'icon_url' => 'Car',
            ],
            [
                'name' => 'heating',
                'display_name' => 'Heating',
                'icon_url' => 'Flame',
            ],
            [
                'name' => 'kitchen',
                'display_name' => 'Kitchen',
                'icon_url' => 'ChefHat',
            ],
            [
                'name' => 'laptop_friendly_workspace',
                'display_name' => 'Laptop friendly workspace',
                'icon_url' => 'Laptop',
            ],
            [
                'name' => 'long_term_stays_allowed',
                'display_name' => 'Long term stays allowed',
                'icon_url' => 'Calendar',
            ],
            [
                'name' => 'microwave',
                'display_name' => 'Microwave',
                'icon_url' => 'Microwave',
            ],
            [
                'name' => 'oven',
                'display_name' => 'Oven',
                'icon_url' => 'ChefHat',
            ],
            [
                'name' => 'private_entrance',
                'display_name' => 'Private entrance',
                'icon_url' => 'DoorOpen',
            ],
            [
                'name' => 'refrigerator',
                'display_name' => 'Refrigerator',
                'icon_url' => 'Refrigerator',
            ],
            [
                'name' => 'smoke_detector',
                'display_name' => 'Smoke detector',
                'icon_url' => 'AlertCircle',
            ],
            [
                'name' => 'stove',
                'display_name' => 'Stove',
                'icon_url' => 'Flame',
            ],
            [
                'name' => 'tv',
                'display_name' => 'TV',
                'icon_url' => 'Tv',
            ],
            [
                'name' => 'washer',
                'display_name' => 'Washer',
                'icon_url' => 'WashingMachine',
            ],
            [
                'name' => 'wireless_internet',
                'display_name' => 'Wifi',
                'icon_url' => 'Wifi',
            ],
        ];

        foreach ($amenities as $amenityData) {
            Amenity::updateOrCreate(
                ['name' => $amenityData['name']],
                [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'display_name' => $amenityData['display_name'],
                    'icon_url' => $amenityData['icon_url'],
                ]
            );
        }
    }
}

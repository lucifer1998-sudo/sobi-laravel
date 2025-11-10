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
            ['name' => 'air_conditioning', 'display_name' => 'Air conditioning', 'icon' => 'Wind'],
            ['name' => 'arcade_games', 'display_name' => 'Arcade games', 'icon' => 'Gamepad2'],
            ['name' => 'baby_bath', 'display_name' => 'Baby bath', 'icon' => 'Baby'],
            ['name' => 'baby_monitor', 'display_name' => 'Baby monitor', 'icon' => 'Radio'],
            ['name' => 'baby_safety_gates', 'display_name' => 'Baby safety gates', 'icon' => 'ShieldCheck'],
            ['name' => 'backyard', 'display_name' => 'Backyard', 'icon' => 'TreePine'],
            ['name' => 'baking_sheet', 'display_name' => 'Baking sheet', 'icon' => 'Cookie'],
            ['name' => 'barbecue_utensils', 'display_name' => 'Barbecue utensils', 'icon' => 'UtensilsCrossed'],
            ['name' => 'bathtub', 'display_name' => 'Bathtub', 'icon' => 'Bath'],
            ['name' => 'batting_cage', 'display_name' => 'Batting cage', 'icon' => 'Baseball'],
            ['name' => 'bbq_grill', 'display_name' => 'BBQ grill', 'icon' => 'Flame'],
            ['name' => 'beach_access', 'display_name' => 'Beach access', 'icon' => 'Umbrella'],
            ['name' => 'beach_essentials', 'display_name' => 'Beach essentials', 'icon' => 'Sun'],
            ['name' => 'beachfront', 'display_name' => 'Beachfront', 'icon' => 'Waves'],
            ['name' => 'bed_linens', 'display_name' => 'Bed linens', 'icon' => 'Bed'],
            ['name' => 'bidet', 'display_name' => 'Bidet', 'icon' => 'Droplets'],
            ['name' => 'bike_parking', 'display_name' => 'Bike parking', 'icon' => 'Bike'],
            ['name' => 'board_games', 'display_name' => 'Board games', 'icon' => 'Puzzle'],
            ['name' => 'body_soap', 'display_name' => 'Body soap', 'icon' => 'Soap'],
            ['name' => 'books_and_reading_material', 'display_name' => 'Books and reading material', 'icon' => 'BookOpen'],
            ['name' => 'bread_maker', 'display_name' => 'Bread maker', 'icon' => 'Croissant'],
            ['name' => 'breakfast', 'display_name' => 'Breakfast', 'icon' => 'Coffee'],
            ['name' => 'building_staff', 'display_name' => 'Building staff', 'icon' => 'UserCog'],
            ['name' => 'carbon_monoxide_alarm', 'display_name' => 'Carbon monoxide alarm', 'icon' => 'AlarmSmoke'],
            ['name' => 'ceiling_fan', 'display_name' => 'Ceiling fan', 'icon' => 'Fan'],
            ['name' => 'childrens_books_and_toys', 'display_name' => 'Children’s books and toys', 'icon' => 'ToyBrick'],
            ['name' => 'childrens_dinnerware', 'display_name' => 'Children’s dinnerware', 'icon' => 'CupSoda'],
            ['name' => 'cleaning_products', 'display_name' => 'Cleaning products', 'icon' => 'Broom'],
            ['name' => 'clothing_storage', 'display_name' => 'Clothing storage', 'icon' => 'Shirt'],
            ['name' => 'coffee', 'display_name' => 'Coffee', 'icon' => 'Coffee'],
            ['name' => 'coffee_maker', 'display_name' => 'Coffee maker', 'icon' => 'Coffee'],
            ['name' => 'conditioner', 'display_name' => 'Conditioner', 'icon' => 'Droplet'],
            ['name' => 'cooking_basics', 'display_name' => 'Cooking basics', 'icon' => 'Utensils'],
            ['name' => 'crib', 'display_name' => 'Crib', 'icon' => 'Baby'],
            ['name' => 'dedicated_workspace', 'display_name' => 'Dedicated workspace', 'icon' => 'Laptop'],
            ['name' => 'dishes_and_silverware', 'display_name' => 'Dishes and silverware', 'icon' => 'UtensilsCrossed'],
            ['name' => 'dishwasher', 'display_name' => 'Dishwasher', 'icon' => 'WashingMachine'],
            ['name' => 'dryer', 'display_name' => 'Dryer', 'icon' => 'Fan'],
            ['name' => 'drying_rack_for_clothing', 'display_name' => 'Drying rack for clothing', 'icon' => 'Hanger'],
            ['name' => 'ev_charger', 'display_name' => 'EV charger', 'icon' => 'PlugZap'],
            ['name' => 'elevator', 'display_name' => 'Elevator', 'icon' => 'MoveVertical'],
            ['name' => 'essentials', 'display_name' => 'Essentials', 'icon' => 'Package'],
            ['name' => 'ethernet_connection', 'display_name' => 'Ethernet connection', 'icon' => 'Network'],
            ['name' => 'exercise_equipment', 'display_name' => 'Exercise equipment', 'icon' => 'Dumbbell'],
            ['name' => 'extra_pillows_and_blankets', 'display_name' => 'Extra pillows and blankets', 'icon' => 'Pillow'],
            ['name' => 'fast_wifi', 'display_name' => 'Fast WiFi', 'icon' => 'WifiHigh'],
            ['name' => 'fire_extinguisher', 'display_name' => 'Fire extinguisher', 'icon' => 'FireExtinguisher'],
            ['name' => 'fire_pit', 'display_name' => 'Fire pit', 'icon' => 'Flame'],
            ['name' => 'first_aid_kit', 'display_name' => 'First aid kit', 'icon' => 'FirstAid'],
            ['name' => 'free_parking', 'display_name' => 'Free parking', 'icon' => 'Car'],
            ['name' => 'freezer', 'display_name' => 'Freezer', 'icon' => 'Snowflake'],
            ['name' => 'game_console', 'display_name' => 'Game console', 'icon' => 'Gamepad2'],
            ['name' => 'gym', 'display_name' => 'Gym', 'icon' => 'Dumbbell'],
            ['name' => 'hair_dryer', 'display_name' => 'Hair dryer', 'icon' => 'Wind'],
            ['name' => 'hangers', 'display_name' => 'Hangers', 'icon' => 'Hanger'],
            ['name' => 'heating', 'display_name' => 'Heating', 'icon' => 'ThermometerSun'],
            ['name' => 'high_chair', 'display_name' => 'High chair', 'icon' => 'Chair'],
            ['name' => 'hot_tub', 'display_name' => 'Hot tub', 'icon' => 'Waves'],
            ['name' => 'hot_water', 'display_name' => 'Hot water', 'icon' => 'Droplets'],
            ['name' => 'hot_water_kettle', 'display_name' => 'Hot water kettle', 'icon' => 'CupHot'],
            ['name' => 'indoor_fireplace', 'display_name' => 'Indoor fireplace', 'icon' => 'Flame'],
            ['name' => 'iron', 'display_name' => 'Iron', 'icon' => 'Iron'],
            ['name' => 'kitchen', 'display_name' => 'Kitchen', 'icon' => 'Utensils'],
            ['name' => 'long_term_stays_allowed', 'display_name' => 'Long term stays allowed', 'icon' => 'CalendarCheck'],
            ['name' => 'luggage_dropoff_allowed', 'display_name' => 'Luggage dropoff allowed', 'icon' => 'Luggage'],
            ['name' => 'microwave', 'display_name' => 'Microwave', 'icon' => 'Microwave'],
            ['name' => 'mini_fridge', 'display_name' => 'Mini fridge', 'icon' => 'Fridge'],
            ['name' => 'mosquito_net', 'display_name' => 'Mosquito net', 'icon' => 'Shield'],
            ['name' => 'outdoor_dining_area', 'display_name' => 'Outdoor dining area', 'icon' => 'Sun'],
            ['name' => 'outdoor_furniture', 'display_name' => 'Outdoor furniture', 'icon' => 'Umbrella'],
            ['name' => 'oven', 'display_name' => 'Oven', 'icon' => 'Oven'],
            ['name' => 'patio_or_balcony', 'display_name' => 'Patio or balcony', 'icon' => 'Layout'],
            ['name' => 'pets_allowed', 'display_name' => 'Pets allowed', 'icon' => 'Dog'],
            ['name' => 'piano', 'display_name' => 'Piano', 'icon' => 'Music'],
            ['name' => 'pool', 'display_name' => 'Pool', 'icon' => 'Waves'],
            ['name' => 'private_entrance', 'display_name' => 'Private entrance', 'icon' => 'DoorClosed'],
            ['name' => 'refrigerator', 'display_name' => 'Refrigerator', 'icon' => 'Fridge'],
            ['name' => 'room_darkening_shades', 'display_name' => 'Room-darkening shades', 'icon' => 'Moon'],
            ['name' => 'security_cameras', 'display_name' => 'Security cameras on property', 'icon' => 'Camera'],
            ['name' => 'shampoo', 'display_name' => 'Shampoo', 'icon' => 'Bottle'],
            ['name' => 'shower_gel', 'display_name' => 'Shower gel', 'icon' => 'Droplet'],
            ['name' => 'smoke_alarm', 'display_name' => 'Smoke alarm', 'icon' => 'AlarmSmoke'],
            ['name' => 'stove', 'display_name' => 'Stove', 'icon' => 'Flame'],
            ['name' => 'tv', 'display_name' => 'TV', 'icon' => 'Tv'],
            ['name' => 'toaster', 'display_name' => 'Toaster', 'icon' => 'Sandwich'],
            ['name' => 'washer', 'display_name' => 'Washer', 'icon' => 'WashingMachine'],
            ['name' => 'wifi', 'display_name' => 'Wifi', 'icon' => 'Wifi'],
            ['name' => 'wine_glasses', 'display_name' => 'Wine glasses', 'icon' => 'Wine'],
        ];

        foreach ($amenities as $amenityData) {
            Amenity::updateOrCreate(
                ['name' => $amenityData['name']],
                [
                    'id' => (string) \Illuminate\Support\Str::uuid(),
                    'display_name' => $amenityData['display_name'],
                    'icon_url' => $amenityData['icon'],
                ]
            );
        }
    }
}

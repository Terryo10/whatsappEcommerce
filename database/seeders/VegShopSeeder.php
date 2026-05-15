<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\DeliverySlot;
use App\Models\DeliveryZone;
use App\Models\Product;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class VegShopSeeder extends Seeder
{
    public function run(): void
    {
        // Categories
        $vegetables = Category::create(['name' => 'Vegetables', 'slug' => 'vegetables', 'sort_order' => 1]);
        $fruits = Category::create(['name' => 'Fruits', 'slug' => 'fruits', 'sort_order' => 2]);
        $grains = Category::create(['name' => 'Grains & Staples', 'slug' => 'grains', 'sort_order' => 3]);

        // Products
        $products = [
            ['name' => 'Tomatoes', 'unit' => 'kg', 'price_usd' => 1.50, 'stock_qty' => 50, 'category_id' => $vegetables->id],
            ['name' => 'Onions', 'unit' => 'kg', 'price_usd' => 0.80, 'stock_qty' => 40, 'category_id' => $vegetables->id],
            ['name' => 'Spinach (Rape)', 'unit' => 'bunch', 'price_usd' => 0.50, 'stock_qty' => 30, 'category_id' => $vegetables->id],
            ['name' => 'Butternut', 'unit' => 'piece', 'price_usd' => 1.20, 'stock_qty' => 25, 'category_id' => $vegetables->id],
            ['name' => 'Cabbage', 'unit' => 'piece', 'price_usd' => 0.90, 'stock_qty' => 20, 'category_id' => $vegetables->id],
            ['name' => 'Banana', 'unit' => 'bunch', 'price_usd' => 1.00, 'stock_qty' => 35, 'category_id' => $fruits->id],
            ['name' => 'Mango', 'unit' => 'piece', 'price_usd' => 0.60, 'stock_qty' => 60, 'category_id' => $fruits->id],
            ['name' => 'Avocado', 'unit' => 'piece', 'price_usd' => 0.80, 'stock_qty' => 45, 'category_id' => $fruits->id],
            ['name' => 'Mealie Meal (10kg)', 'unit' => 'piece', 'price_usd' => 8.50, 'stock_qty' => 15, 'category_id' => $grains->id],
            ['name' => 'Rice (2kg)', 'unit' => 'piece', 'price_usd' => 3.50, 'stock_qty' => 20, 'category_id' => $grains->id],
        ];

        foreach ($products as $data) {
            Product::create(array_merge($data, [
                'slug' => Str::slug($data['name']),
                'description' => "Fresh {$data['name']} delivered to your door.",
                'price_zwl' => $data['price_usd'] * 3600,
                'low_stock_threshold' => 5,
                'is_active' => true,
                'is_whatsapp_visible' => true,
            ]));
        }

        // Delivery Zones
        DeliveryZone::create([
            'name' => 'Harare North',
            'suburb_keywords' => ['Borrowdale', 'Glen Lorne', 'Chisipite', 'Highlands', 'Msasa', 'Greendale'],
            'base_fee' => 2.00,
            'per_km_fee' => 0,
            'free_delivery_above' => 30.00,
            'estimated_minutes' => 45,
        ]);

        DeliveryZone::create([
            'name' => 'Harare Central',
            'suburb_keywords' => ['Avondale', 'Milton Park', 'Belgravia', 'Eastlea', 'Newlands', 'Mabelreign'],
            'base_fee' => 1.50,
            'per_km_fee' => 0,
            'free_delivery_above' => 25.00,
            'estimated_minutes' => 30,
        ]);

        DeliveryZone::create([
            'name' => 'Harare South',
            'suburb_keywords' => ['Budiriro', 'Mufakose', 'Glenview', 'Glen Norah', 'Highfield'],
            'base_fee' => 3.00,
            'per_km_fee' => 0.10,
            'free_delivery_above' => 40.00,
            'estimated_minutes' => 60,
        ]);

        DeliveryZone::create([
            'name' => 'Chitungwiza',
            'suburb_keywords' => ['Chitungwiza', 'St Marys', 'Zengeza', 'Unit K'],
            'base_fee' => 4.50,
            'per_km_fee' => 0.15,
            'free_delivery_above' => 50.00,
            'estimated_minutes' => 90,
        ]);

        // Delivery Slots (next 7 days)
        for ($i = 0; $i <= 6; $i++) {
            $date = Carbon::today()->addDays($i);
            $dayOfWeek = $date->dayOfWeek;

            if ($dayOfWeek === 0) continue; // Skip Sunday

            $slots = [
                ['label' => 'Morning 7am–12pm', 'time_from' => '07:00', 'time_to' => '12:00', 'max_orders' => $dayOfWeek === 6 ? 30 : 20],
                ['label' => 'Afternoon 12pm–5pm', 'time_from' => '12:00', 'time_to' => '17:00', 'max_orders' => $dayOfWeek === 6 ? 15 : 20],
            ];

            if ($dayOfWeek !== 6) {
                $slots[] = ['label' => 'Evening 5pm–8pm', 'time_from' => '17:00', 'time_to' => '20:00', 'max_orders' => 10];
            }

            foreach ($slots as $slot) {
                DeliverySlot::create(array_merge($slot, ['date' => $date->toDateString(), 'is_available' => true]));
            }
        }
    }
}

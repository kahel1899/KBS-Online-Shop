<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            ['Nail Polish', 'nail-polish'],
            ['Gel & Acrylic', 'gel-and-acrylic'],
            ['Press-on Nails', 'press-on-nails'],
            ['Nail Tools', 'nail-tools'],
            ['Nail Art & Accessories', 'nail-art-and-accessories'],
        ];

        foreach ($categories as $index => [$name, $slug]) {
            // firstOrCreate: safe to run many times, never overwrites your edits
            Category::firstOrCreate(
                ['slug' => $slug],
                ['name' => $name, 'sort_order' => $index + 1, 'is_active' => true]
            );
        }
    }
}

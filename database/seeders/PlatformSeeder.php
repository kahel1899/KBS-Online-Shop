<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Platform;

class PlatformSeeder extends Seeder
{
    public function run()
    {
        // firstOrCreate: safe to run many times, never overwrites fees you changed
        $platforms = [
            ['TikTok', 10.00],
            ['Shopee', 15.00],
            ['Walk-in', 0.00],
            ['Online', 0.00],
        ];

        foreach ($platforms as [$name, $fee]) {
            Platform::firstOrCreate(
                ['name' => $name],
                ['fee_percentage' => $fee]
            );
        }
    }
}

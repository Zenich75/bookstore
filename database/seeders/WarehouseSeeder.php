<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    private const WAREHOUSES = [
        ['name' => 'Central Warehouse', 'city' => 'Kyiv', 'address' => '12 Khreshchatyk St'],
        ['name' => 'West Hub', 'city' => 'Lviv', 'address' => '5 Horodotska St'],
        ['name' => 'East Hub', 'city' => 'Kharkiv', 'address' => '40 Sumska St'],
        ['name' => 'South Hub', 'city' => 'Odesa', 'address' => '7 Deribasivska St'],
        ['name' => 'Dnipro Depot', 'city' => 'Dnipro', 'address' => '101 Yavornytskoho Ave'],
    ];

    public function run(): void
    {
        foreach (self::WAREHOUSES as $attributes) {
            Warehouse::create($attributes);
        }
    }
}

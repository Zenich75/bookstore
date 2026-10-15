<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    private const WAREHOUSES = [
        ['name' => 'Центральний склад', 'city' => 'Київ', 'address' => 'вул. Хрещатик, 12'],
        ['name' => 'Західний склад', 'city' => 'Львів', 'address' => 'вул. Городоцька, 5'],
        ['name' => 'Східний склад', 'city' => 'Харків', 'address' => 'вул. Сумська, 40'],
        ['name' => 'Південний склад', 'city' => 'Одеса', 'address' => 'вул. Дерибасівська, 7'],
        ['name' => 'Дніпровський склад', 'city' => 'Дніпро', 'address' => 'просп. Яворницького, 101'],
    ];

    public function run(): void
    {
        foreach (self::WAREHOUSES as $attributes) {
            Warehouse::create($attributes);
        }
    }
}

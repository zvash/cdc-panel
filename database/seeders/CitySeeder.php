<?php

namespace Database\Seeders;

use App\Models\City;
use App\Traits\Seeder\StubHandler;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CitySeeder extends Seeder
{
    use StubHandler;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->cities()->each(function ($city) {
            City::create($city);
        });
    }
}

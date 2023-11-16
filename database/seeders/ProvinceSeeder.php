<?php

namespace Database\Seeders;

use App\Models\Province;
use App\Traits\Seeder\StubHandler;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProvinceSeeder extends Seeder
{
    use StubHandler;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->provinces()->each(function ($province) {
            Province::create($province);
        });
    }
}

<?php

namespace Database\Seeders;

use App\Models\ClientType;
use App\Models\ProvinceTax;
use App\Traits\Seeder\StubHandler;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ProvinceTaxSeeder extends Seeder
{
    use StubHandler;
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->provinceTaxes()->each(function ($provinceTax) {
            ProvinceTax::create($provinceTax);
        });
    }
}

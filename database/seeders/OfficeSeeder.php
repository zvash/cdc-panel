<?php

namespace Database\Seeders;

use App\Models\Office;
use App\Traits\Seeder\StubHandler;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class OfficeSeeder extends Seeder
{
    use StubHandler;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->offices()->each(function ($office) {
            Office::create($office);
        });
    }
}

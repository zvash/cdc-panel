<?php

namespace Database\Seeders;

use App\Models\AppraisalType;
use App\Traits\Seeder\StubHandler;
use Illuminate\Database\Seeder;

class AppraisalTypeSeeder extends Seeder
{
    use StubHandler;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->appraisalTypes()->each(function ($appraisalType) {
            AppraisalType::create($appraisalType);
        });
    }
}

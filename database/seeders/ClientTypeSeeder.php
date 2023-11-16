<?php

namespace Database\Seeders;

use App\Models\ClientType;
use App\Traits\Seeder\StubHandler;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClientTypeSeeder extends Seeder
{
    use StubHandler;
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->clientTypes()->each(function ($clientType) {
            ClientType::create($clientType);
        });
    }
}

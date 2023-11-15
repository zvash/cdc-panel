<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Traits\Seeder\StubHandler;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    use StubHandler;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->roles()->each(function ($role) {
            Role::create($role);
        });
    }
}

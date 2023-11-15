<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Traits\Seeder\StubHandler;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class WebPermissionSeeder extends Seeder
{
    use StubHandler;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->permissions()->each(function ($permission) {
            $permission = Permission::query()->create($permission);
            if (!$permission->hasRole('SuperAdmin')) {
                $permission->assignRole('SuperAdmin');
            }
            if (!$permission->hasRole('Admin')) {
                $permission->assignRole('Admin');
            }
            if (Str::endsWith($permission->name, 'Self') && !$permission->hasRole('Appraiser')) {
                $permission->assignRole('Appraiser');

            }
        });
    }
}

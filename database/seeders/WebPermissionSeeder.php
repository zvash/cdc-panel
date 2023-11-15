<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\User;
use App\Traits\Seeder\StubHandler;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

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
//            $model = Permission::query()->firstOrCreate($permission);
//            if (!$model->hasRole('Administrator')) {
//                $model->assignRole('Administrator');
//            }
            //Permission::query()->firstOrCreate($permission)->syncRoles('Administrator');
        });

//        User::all()->each(function ($user) {
//            //$user->syncRoles($user->id > 1 ? 'Administrator' : 'Superadmin');
//        });
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use App\Traits\Seeder\Storage;
use App\Traits\Seeder\StubHandler;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    use StubHandler, Storage;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->users()->each(function ($user) {
            $user = User::create(array_merge($user, [
                'password' => bcrypt($user['password']),
                'avatar' => $this->store('users', $user['avatar'] ?? null),
            ]));
            if ($user->id == 1) {
                $user->assignRole('Supervisor');
            } else if ($user->id == 2) {
                $user->assignRole('SuperAdmin');
            } else if ($user->id < 5) {
                $user->assignRole('Admin');
            } else {
                $user->assignRole('Appraiser');
            }
        });
    }
}

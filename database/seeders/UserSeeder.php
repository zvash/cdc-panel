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
            User::create(array_merge($user, [
                'password' => bcrypt($user['password']),
                'avatar' => $this->store('users', $user['avatar'] ?? null),
            ]));
        });
    }
}

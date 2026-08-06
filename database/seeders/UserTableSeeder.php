<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $users = array(
            [
                'name' => 'Administrator',
                'email' => 'admin@mail.com',
                'password' => bcrypt('1234'),
                'role' => 'admin',
                'foto' => '/img/user.jpg',
                'level' => 1,
                'can_create' => true,
                'can_read' => true,
                'can_update' => true,
                'can_delete' => true,
            ],
            [
                'name' => 'Cashier',
                'email' => 'cashier@mail.com',
                'password' => bcrypt('1234'),
                'role' => 'cashier',
                'foto' => '/img/user.jpg',
                'level' => 2,
                'sal_read' => true,
                'sal_create' => true,
            ],
        );

        array_map(function (array $user) {
            User::query()->updateOrCreate(
                ['email' => $user['email']],
                $user
            );
        }, $users);
    }
}

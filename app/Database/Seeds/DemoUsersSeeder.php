<?php

namespace App\Database\Seeds;

use App\Helpers\Hash;
use CodeIgniter\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    public function run()
    {
        $users = [
            [
                'name'          => 'Rina Supervisor',
                'username'      => 'supervisor',
                'email'         => 'supervisor@farmagitechs.test',
                'password_hash' => Hash::make('supervisor123'),
                'role'          => 'supervisor',
                'is_active'     => 1,
            ],
            [
                'name'          => 'Dewi Petugas',
                'username'      => 'petugas',
                'email'         => 'petugas@farmagitechs.test',
                'password_hash' => Hash::make('petugas123'),
                'role'          => 'reception',
                'is_active'     => 1,
            ],
        ];

        $this->db->table('users')->whereIn('username', ['supervisor', 'petugas'])->delete();
        $this->db->table('users')->insertBatch($users);
    }
}

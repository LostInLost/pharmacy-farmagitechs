<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class DemoUsersSeeder extends Seeder
{
    public function run()
    {
        $users = [
            [
                'username'      => 'supervisor',
                'password_hash' => password_hash('supervisor123', PASSWORD_DEFAULT),
                'role'          => 'supervisor',
                'is_active'     => 1,
            ],
            [
                'username'      => 'petugas',
                'password_hash' => password_hash('petugas123', PASSWORD_DEFAULT),
                'role'          => 'penerimaan',
                'is_active'     => 1,
            ],
        ];

        $this->db->table('users')->where('username', 'supervisor')->delete();
        $this->db->table('users')->where('username', 'petugas')->delete();
        $this->db->table('users')->insertBatch($users);
    }
}

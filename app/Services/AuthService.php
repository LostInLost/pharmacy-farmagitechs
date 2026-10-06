<?php

namespace App\Services;

use App\Helpers\Hash;
use App\Repositories\UserRepository;

class AuthService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }

    public function attempt(string $username, string $password): ?array
    {
        $user = $this->users->findByUsername($username);

        if ($user === null || (int) $user['is_active'] !== 1) {
            return null;
        }

        if (! Hash::match($password, $user['password_hash'])) {
            return null;
        }

        unset($user['password_hash']);

        return $user;
    }

    public function login(array $user): void
    {
        session()->regenerate();
        session()->set([
            'user_id'   => (int) $user['id'],
            'user_name' => $user['name'],
            'username'  => $user['username'] ?? '',
            'role'      => $user['role'],
        ]);
    }

    public function logout(): void
    {
        session()->destroy();
    }
}

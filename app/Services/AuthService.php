<?php

namespace App\Services;

use App\Repositories\UserRepository;

class AuthService
{
    public function __construct(
        private readonly UserRepository $users = new UserRepository(),
    ) {
    }
}

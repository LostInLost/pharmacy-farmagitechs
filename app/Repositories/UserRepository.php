<?php

namespace App\Repositories;

use App\Models\UserModel;

class UserRepository
{
    public function __construct(
        private readonly UserModel $model = new UserModel(),
    ) {
    }

    public function findByUsername(string $username): ?array
    {
        return $this->model->where('username', $username)->first();
    }

    public function findByEmail(string $email): ?array
    {
        return $this->model->where('email', $email)->first();
    }

    public function findById(int $id): ?array
    {
        return $this->model->find($id);
    }
}

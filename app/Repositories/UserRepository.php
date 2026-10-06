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
        return $this->normalize($this->model->where('username', $username)->first());
    }

    public function findByEmail(string $email): ?array
    {
        return $this->normalize($this->model->where('email', $email)->first());
    }

    public function findById(int $id): ?array
    {
        return $this->normalize($this->model->find($id));
    }

    private function normalize(?array $row): ?array
    {
        if ($row === null) {
            return null;
        }

        $row['id']        = (int) $row['id'];
        $row['is_active'] = (int) $row['is_active'];

        return $row;
    }
}

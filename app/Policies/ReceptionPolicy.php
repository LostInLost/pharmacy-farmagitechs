<?php

namespace App\Policies;

use Config\Permissions;

class ReceptionPolicy
{
    public function __construct(
        private readonly Permissions $permissions = new Permissions(),
    ) {
    }

    public function canUpdate(array $actor, array $reception): bool
    {
        $role = $actor['role'] ?? '';

        if ($this->permissions->roleHas($role, Permissions::RECEIPT_UPDATE_ANY)) {
            return true;
        }

        if (! $this->permissions->roleHas($role, Permissions::RECEIPT_UPDATE_OWN)) {
            return false;
        }

        return (int) $reception['created_by'] === (int) $actor['id'];
    }
}

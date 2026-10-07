<?php

namespace App\Policies;

use Config\Permissions;

/**
 * Master obat tidak memiliki kepemilikan per baris seperti penerimaan:
 * haknya murni per peran, jadi tidak ada parameter entitas.
 */
class MedicinePolicy
{
    public function __construct(
        private readonly Permissions $permissions = new Permissions(),
    ) {
    }

    public function canView(array $actor): bool
    {
        return $this->permissions->roleHas($actor['role'] ?? '', Permissions::MEDICINE_VIEW);
    }

    public function canWrite(array $actor): bool
    {
        return $this->permissions->roleHas($actor['role'] ?? '', Permissions::MEDICINE_WRITE);
    }
}

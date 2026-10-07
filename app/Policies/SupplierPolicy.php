<?php

namespace App\Policies;

use Config\Permissions;

/**
 * Master pemasok tidak memiliki kepemilikan per baris seperti penerimaan:
 * haknya murni per peran, jadi tidak ada parameter entitas.
 */
class SupplierPolicy
{
    public function __construct(
        private readonly Permissions $permissions = new Permissions(),
    ) {
    }

    public function canView(array $actor): bool
    {
        return $this->permissions->roleHas($actor['role'] ?? '', Permissions::SUPPLIER_VIEW);
    }

    public function canWrite(array $actor): bool
    {
        return $this->permissions->roleHas($actor['role'] ?? '', Permissions::SUPPLIER_WRITE);
    }
}

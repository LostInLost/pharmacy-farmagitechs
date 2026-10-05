<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Permissions extends BaseConfig
{
    public const ROLE_PENERIMAAN = 'penerimaan';
    public const ROLE_SUPERVISOR = 'supervisor';

    public const RECEIPT_CREATE     = 'receipt.create';
    public const RECEIPT_VIEW       = 'receipt.view';
    public const RECEIPT_UPDATE_OWN = 'receipt.update-own';
    public const RECEIPT_UPDATE_ANY = 'receipt.update-any';

    /**
     * @var array<string, list<string>>
     */
    public array $roles = [
        self::ROLE_PENERIMAAN => [
            self::RECEIPT_CREATE,
            self::RECEIPT_VIEW,
            self::RECEIPT_UPDATE_OWN,
        ],
        self::ROLE_SUPERVISOR => [
            self::RECEIPT_CREATE,
            self::RECEIPT_VIEW,
            self::RECEIPT_UPDATE_OWN,
            self::RECEIPT_UPDATE_ANY,
        ],
    ];

    public function roleHas(string $role, string $permission): bool
    {
        return in_array($permission, $this->roles[$role] ?? [], true);
    }
}

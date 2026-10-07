<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Permissions extends BaseConfig
{
    public const ROLE_RECEPTION  = 'reception';
    public const ROLE_SUPERVISOR = 'supervisor';

    public const RECEIPT_CREATE     = 'receipt.create';
    public const RECEIPT_VIEW       = 'receipt.view';
    public const RECEIPT_UPDATE_OWN = 'receipt.update-own';
    public const RECEIPT_UPDATE_ANY = 'receipt.update-any';

    public const MEDICINE_VIEW  = 'medicine.view';
    public const MEDICINE_WRITE = 'medicine.write';

    /**
     * @var array<string, list<string>>
     */
    public array $roles = [
        self::ROLE_RECEPTION => [
            self::RECEIPT_CREATE,
            self::RECEIPT_VIEW,
            self::RECEIPT_UPDATE_OWN,
            self::MEDICINE_VIEW,
        ],
        self::ROLE_SUPERVISOR => [
            self::RECEIPT_CREATE,
            self::RECEIPT_VIEW,
            self::RECEIPT_UPDATE_OWN,
            self::RECEIPT_UPDATE_ANY,
            self::MEDICINE_VIEW,
            self::MEDICINE_WRITE,
        ],
    ];

    public function roleHas(string $role, string $permission): bool
    {
        return in_array($permission, $this->roles[$role] ?? [], true);
    }
}

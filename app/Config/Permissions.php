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
        return in_array($permission, $this->forRole($role), true);
    }

    /**
     * Daftar permission sebuah role, siap dikirim ke klien (`GET /api/me`,
     * `POST /api/login`) sebagai array datar string.
     *
     * Bentuk datar dipilih agar kelak bisa dipindah apa adanya ke klaim JWT
     * cookie tanpa mengubah kontrak frontend. Role tak dikenal → `[]`
     * (fail-closed: UI hanya menyembunyikan aksi, penegakan tetap di server).
     *
     * @return list<string>
     */
    public function forRole(string $role): array
    {
        return $this->roles[$role] ?? [];
    }
}

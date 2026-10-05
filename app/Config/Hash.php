<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Hash extends BaseConfig
{
    /**
     * Algoritma hash kata sandi: bcrypt, argon2i, atau argon2id.
     * Algoritma yang tidak didukung PHP di mesin ini otomatis jatuh ke bcrypt.
     */
    public string $algo = 'argon2id';

    /**
     * Cost bcrypt (4-31). Diabaikan saat algoritma argon2.
     */
    public int $bcryptCost = 12;

    /**
     * Memori argon2 dalam KiB.
     */
    public int $memoryCost = 65536;

    /**
     * Time cost argon2.
     */
    public int $timeCost = 4;

    /**
     * Jumlah thread argon2.
     */
    public int $threads = 1;

    /**
     * @return array{algo: int|string, options: array<string, int>}
     */
    public function resolve(): array
    {
        $algo = strtolower($this->algo);

        if ($algo === 'argon2id' && defined('PASSWORD_ARGON2ID')) {
            return $this->argon(constant('PASSWORD_ARGON2ID'));
        }

        if ($algo === 'argon2i' && defined('PASSWORD_ARGON2I')) {
            return $this->argon(constant('PASSWORD_ARGON2I'));
        }

        return [
            'algo'    => PASSWORD_BCRYPT,
            'options' => ['cost' => max(4, min(31, $this->bcryptCost))],
        ];
    }

    /**
     * @param int|string $constant
     *
     * @return array{algo: int|string, options: array<string, int>}
     */
    private function argon($constant): array
    {
        return [
            'algo'    => $constant,
            'options' => [
                'memory_cost' => max(1, $this->memoryCost),
                'time_cost'   => max(1, $this->timeCost),
                'threads'     => max(1, $this->threads),
            ],
        ];
    }
}

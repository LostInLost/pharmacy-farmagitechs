<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Cross-Origin Resource Sharing (CORS) Configuration
 *
 * Hanya origin frontend (dev server Astro, http://localhost:4321) yang
 * diizinkan — tanpa wildcard, sesuai prinsip least privilege. Nilai header
 * `Access-Control-Allow-Origin` tidak pernah diambil dari request, jadi
 * origin asing tidak mungkin ter-echo.
 *
 * Catatan cookie: frontend (localhost:4321) dan backend (localhost:8080)
 * berbagi host `localhost`, sehingga keduanya same-site (port tidak
 * menentukan site) dan cookie session `ci_session` (SameSite=Lax) tetap
 * terkirim pada fetch lintas origin dengan `credentials: 'include'`.
 *
 * @see https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
 */
class Cors extends BaseConfig
{
    /**
     * The default CORS configuration.
     *
     * @var array{
     *      allowedOrigins: list<string>,
     *      allowedOriginsPatterns: list<string>,
     *      supportsCredentials: bool,
     *      allowedHeaders: list<string>,
     *      exposedHeaders: list<string>,
     *      allowedMethods: list<string>,
     *      maxAge: int,
     *  }
     */
    public array $default = [
        /**
         * Origin frontend saja. Origin dihitung dari skema + host + port.
         * Tambahkan origin produksi frontend di sini saat deploy.
         */
        'allowedOrigins' => [
            'http://localhost:4321',
        ],

        'allowedOriginsPatterns' => [],

        /**
         * Wajib true: frontend mengirim cookie session `ci_session` dan
         * cookie CSRF pada setiap request (fetch `credentials: 'include'`).
         */
        'supportsCredentials' => true,

        /**
         * Header yang dikirim frontend: `Content-Type: application/json`
         * (tidak termasuk safelisted) dan `X-CSRF-TOKEN`.
         */
        'allowedHeaders' => ['Content-Type', 'X-CSRF-TOKEN'],

        /**
         * Token CSRF berotasi setiap mutasi; frontend harus bisa membaca
         * nilai terbaru dari header respons, sehingga header ini diekspos.
         */
        'exposedHeaders' => ['X-CSRF-TOKEN'],

        /**
         * Metode yang dipakai API saat ini: GET (stok/receipts/referensi),
         * POST (login/logout/receipts), PUT (update receipts), OPTIONS
         * (preflight).
         */
        'allowedMethods' => ['GET', 'POST', 'PUT', 'OPTIONS'],

        'maxAge' => 7200,
    ];
}

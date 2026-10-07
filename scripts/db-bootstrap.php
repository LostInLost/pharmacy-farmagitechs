<?php

declare(strict_types=1);

/**
 * Titik masuk script database `composer` (`db:migrate`, `db:seed`,
 * `db:bootstrap`, `db:refresh`).
 *
 * Rantai `composer run` biasa tidak bisa dipakai sebagai penjaga: setiap
 * `php spark <command>` keluar dengan kode 0 walau command-nya gagal (seeder
 * tidak ada, migrasi meledak, koneksi DB mati), karena `spark` menangkap
 * exception, mencetak trace lewat view `errors/cli/error_exception.php`, lalu
 * mengembalikan `EXIT_SUCCESS`. Akibatnya `composer db:seed` tetap melanjutkan
 * seeder kedua di atas database yang belum siap dan pelaporannya menyesatkan.
 *
 * Skrip ini menjalankan langkah yang sama, tetapi membaca keluaran tiap
 * `spark`, berhenti di kegagalan pertama, dan mengembalikan kode keluar non-nol
 * supaya `composer` tidak melaporkan sukses palsu.
 */

$modes = [
    'migrate' => [
        ['label' => 'migrate', 'args' => ['migrate']],
    ],
    'seed' => [
        ['label' => 'db:seed StockSeeder', 'args' => ['db:seed', 'StockSeeder']],
        ['label' => 'db:seed DemoUsersSeeder', 'args' => ['db:seed', 'DemoUsersSeeder']],
    ],
    'bootstrap' => [
        ['label' => 'migrate', 'args' => ['migrate']],
        ['label' => 'db:seed StockSeeder', 'args' => ['db:seed', 'StockSeeder']],
        ['label' => 'db:seed DemoUsersSeeder', 'args' => ['db:seed', 'DemoUsersSeeder']],
    ],
    'refresh' => [
        ['label' => 'migrate:refresh', 'args' => ['migrate:refresh']],
        ['label' => 'db:seed StockSeeder', 'args' => ['db:seed', 'StockSeeder']],
        ['label' => 'db:seed DemoUsersSeeder', 'args' => ['db:seed', 'DemoUsersSeeder']],
    ],
];

$mode = $argv[1] ?? 'bootstrap';

if (! isset($modes[$mode])) {
    fwrite(STDERR, 'Mode tidak dikenal: ' . $mode . ' (pakai ' . implode(', ', array_keys($modes)) . ').' . PHP_EOL);

    exit(1);
}

// `migrate:refresh` menghapus seluruh tabel; jangan pernah jalan di production.
if ($mode === 'refresh' && environment() === 'production') {
    fwrite(STDERR, 'Refresh tidak dijalankan: environment production.' . PHP_EOL);

    exit(1);
}

$spark = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(dirname(__DIR__) . '/spark');

foreach ($modes[$mode] as $step) {
    $command = $spark . ' ' . implode(' ', array_map('escapeshellarg', $step['args']));

    fwrite(STDOUT, PHP_EOL . '$ ' . $step['label'] . PHP_EOL);

    $lines  = [];
    $status = 1;

    exec($command . ' 2>&1', $lines, $status);

    fwrite(STDOUT, implode(PHP_EOL, $lines) . PHP_EOL);

    if (sparkFailed($lines)) {
        fwrite(STDERR, PHP_EOL . 'Gagal pada langkah: ' . $step['label'] . PHP_EOL);

        exit(1);
    }
}

fwrite(STDOUT, PHP_EOL . 'Selesai: ' . $mode . PHP_EOL);

exit(0);

/**
 * Kegagalan dikenali dari keluaran, bukan kode keluar: `spark` selalu
 * mengembalikan 0 karena command database menangkap Throwable, mencetak trace
 * lewat view `errors/cli/error_exception.php`, lalu selesai tanpa melempar apa
 * pun. View itu selalu membuka dengan baris `[KelasException]`, jadi baris
 * berkurung yang memuat Exception/Error/Throwable jadi penandanya — mencakup
 * exception framework maupun bawaan PHP (mis. `mysqli_sql_exception` saat
 * koneksi DB mati). Baris "Migration failed!" ikut diperiksa karena migrasi
 * yang gagal dipanggil lewat `CLI::error`, bukan lewat view exception.
 *
 * @param list<string> $lines
 */
function sparkFailed(array $lines): bool
{
    foreach ($lines as $line) {
        $line = trim($line);

        if (preg_match('/^\[.*(Exception|Error|Throwable).*\]$/i', $line) === 1) {
            return true;
        }

        if (str_contains($line, 'Migration failed!')) {
            return true;
        }
    }

    return false;
}

/**
 * Environment dibaca dari `.env` (`CI_ENVIRONMENT = ...`) tanpa mem-bootstrap
 * CodeIgniter, cukup untuk menolak refresh di production.
 */
function environment(): string
{
    $env = dirname(__DIR__) . '/.env';

    if (! is_file($env)) {
        return 'development';
    }

    foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (preg_match('/^\s*CI_ENVIRONMENT\s*=\s*[\'"]?([^\'"]+?)[\'"]?\s*$/', $line, $matches) === 1) {
            return trim($matches[1]);
        }
    }

    return 'development';
}

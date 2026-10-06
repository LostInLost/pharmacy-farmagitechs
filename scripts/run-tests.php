<?php

declare(strict_types=1);

/**
 * Titik masuk `composer run test`.
 *
 * PHPUnit memakai interpreter PHP yang menjalankan Composer. Bila `mysqli`
 * tidak aktif di php.ini interpreter tersebut, test database MySQLi gagal
 * terhubung, jadi skrip ini menjalankan ulang PHP dengan
 * `-d extension=mysqli` sebelum menyerahkan kendali ke PHPUnit.
 */

$retryEnv = 'RUN_TESTS_MYSQLI_RETRY';

$root       = dirname(__DIR__);
$phpunitBin = $root . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . 'phpunit';
$args       = array_slice($argv, 1);
$argSuffix  = $args === [] ? '' : ' ' . implode(' ', array_map('escapeshellarg', $args));
$retrying   = getenv($retryEnv) === '1';
$mysqli     = extension_loaded('mysqli');

if (! is_file($phpunitBin)) {
    fwrite(STDERR, 'PHPUnit tidak ditemukan; jalankan `composer install` lebih dulu.' . PHP_EOL);

    exit(1);
}

if (! $mysqli && ! $retrying) {
    fwrite(STDOUT, 'mysqli tidak aktif di php.ini ini; menjalankan ulang PHP dengan -d extension=mysqli.' . PHP_EOL);

    putenv($retryEnv . '=1');

    $status  = 1;
    $command = escapeshellarg(PHP_BINARY) . ' -d extension=mysqli ' . escapeshellarg(__FILE__) . $argSuffix;
    passthru($command, $status);

    exit($status);
}

if (! $mysqli) {
    fwrite(STDERR, implode(PHP_EOL, [
        'Ekstensi mysqli tidak bisa diaktifkan.',
        '  PHP     : ' . PHP_BINARY,
        '  php.ini : ' . (php_ini_loaded_file() ?: '(tidak ada)'),
        'Aktifkan `extension=mysqli` pada php.ini tersebut, atau jalankan test',
        'dengan PHP yang sudah memuat mysqli (mis. PHP bawaan Laragon/XAMPP).',
    ]) . PHP_EOL);

    exit(1);
}

$mysqliFlag = $retrying ? ' -d extension=mysqli' : '';
$command    = escapeshellarg(PHP_BINARY) . $mysqliFlag . ' ' . escapeshellarg($phpunitBin) . $argSuffix;
$status     = 1;
passthru($command, $status);

exit($status);

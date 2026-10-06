<?php

declare(strict_types=1);

/**
 * Titik masuk `composer run test`.
 *
 * Secara default skrip menjalankan PHPUnit persis seperti `vendor/bin/phpunit`,
 * sehingga konfigurasi dan fallback bawaan CodeIgniter (termasuk SQLite3
 * `:memory:` di Config\Database::$tests) tetap berlaku apa adanya. Skrip hanya
 * menambah dua hal:
 *
 * 1. Direktori kerja dipindah ke root proyek, karena PHPUnit mencari
 *    phpunit.dist.xml di direktori kerja saat ini.
 * 2. Bila test gagal karena ekstensi mysqli tidak aktif, test diulang dengan
 *    `-d extension=mysqli`. Driver test tidak ditebak skrip ini, jadi proyek
 *    yang memakai MySQLi tetap jalan di php.ini tanpa mysqli, dan proyek yang
 *    memakai fallback SQLite3 tidak dipaksa memuat mysqli.
 *
 * Binary PHPUnit dicari mengikuti aturan Composer: COMPOSER_BIN_DIR, config
 * `bin-dir` / `vendor-dir` di composer.json, PATH (disuntik Composer saat
 * menjalankan script), lalu default `vendor/bin`.
 */

$mysqliHint = 'required PHP extension "mysqli" is not loaded';
$root       = dirname(__DIR__);

if (! chdir($root)) {
    fwrite(STDERR, 'Tidak bisa pindah ke direktori proyek: ' . $root . PHP_EOL);

    exit(1);
}

$phpunit = findPhpunitBinary($root);

if ($phpunit === null) {
    fwrite(STDERR, 'PHPUnit tidak ditemukan; jalankan `composer install` lebih dulu.' . PHP_EOL);

    exit(1);
}

$args = array_slice($argv, 1);

// Interpreter ini sudah memuat mysqli: tidak ada yang perlu disiapkan.
if (extension_loaded('mysqli')) {
    streamCommand(phpunitCommand($phpunit, $args));
}

// Proses ulang memakai php.ini yang sama supaya PHPRC atau opsi `-c` yang
// dipakai pemanggil tidak hilang. php_ini_loaded_file() mengembalikan false
// bila tidak ada php.ini yang dimuat (mis. `php -n`).
$iniFile = php_ini_loaded_file() ?: null;

// Tanpa mysqli, jalankan dulu seperti biasa supaya driver yang benar-benar
// dipakai konfigurasi proyek (mis. fallback SQLite3) yang menentukan hasil.
[$status, $output] = captureCommand(phpunitCommand($phpunit, $args, iniFile: $iniFile));

if ($status === 0 || ! str_contains($output, $mysqliHint)) {
    fwrite($status === 0 ? STDOUT : STDERR, $output);

    exit($status);
}

if (! mysqliCanBeLoaded($iniFile)) {
    fwrite(STDOUT, $output);
    fwrite(STDERR, implode(PHP_EOL, [
        'Ekstensi mysqli tidak bisa dimuat, padahal database test memakai driver MySQLi.',
        '  PHP     : ' . PHP_BINARY,
        '  php.ini : ' . (php_ini_loaded_file() ?: '(tidak ada)'),
        'Aktifkan `extension=mysqli` pada php.ini tersebut, atau jalankan test',
        'dengan PHP yang sudah memuat mysqli (mis. PHP bawaan Laragon/XAMPP).',
    ]) . PHP_EOL);

    exit($status);
}

fwrite(STDOUT, 'mysqli tidak aktif di php.ini ini; test diulang dengan -d extension=mysqli.' . PHP_EOL);

streamCommand(phpunitCommand($phpunit, $args, withMysqli: true, iniFile: $iniFile));

/**
 * Menyusun perintah PHPUnit dengan interpreter PHP yang sedang berjalan.
 *
 * @param list<string> $args
 */
function phpunitCommand(string $phpunit, array $args, bool $withMysqli = false, ?string $iniFile = null): string
{
    $flags = $withMysqli ? ' -d extension=mysqli' : '';

    if ($iniFile !== null && $iniFile !== '') {
        $flags .= ' -c ' . escapeshellarg($iniFile);
    }

    $suffix = $args === [] ? '' : ' ' . implode(' ', array_map('escapeshellarg', $args));

    return escapeshellarg(PHP_BINARY) . $flags . ' ' . escapeshellarg($phpunit) . $suffix;
}

/**
 * Menjalankan perintah dengan keluaran langsung ke terminal.
 */
function streamCommand(string $command): never
{
    $status = 1;

    passthru($command, $status);

    exit($status);
}

/**
 * Menjalankan perintah sambil menampung keluaran beserta stderr-nya.
 *
 * @return array{int, string}
 */
function captureCommand(string $command): array
{
    $lines  = [];
    $status = 1;

    exec($command . ' 2>&1', $lines, $status);

    return [$status, $lines === [] ? '' : implode(PHP_EOL, $lines) . PHP_EOL];
}

/**
 * Mengecek apakah interpreter ini bisa memuat mysqli dengan flag `-d`.
 */
function mysqliCanBeLoaded(?string $iniFile): bool
{
    $ini = $iniFile === null || $iniFile === '' ? '' : ' -c ' . escapeshellarg($iniFile);

    $lines  = [];
    $status = 1;

    exec(
        escapeshellarg(PHP_BINARY) . $ini . ' -d extension=mysqli -r "exit(extension_loaded(\'mysqli\') ? 0 : 1);" 2>&1',
        $lines,
        $status,
    );

    return $status === 0;
}

/**
 * Mencari binary PHPUnit mengikuti resolusi bin-dir milik Composer.
 */
function findPhpunitBinary(string $root): ?string
{
    $config    = composerConfig($root);
    $vendorDir = composerPath($root, envValue('COMPOSER_VENDOR_DIR') ?? configString($config, 'vendor-dir') ?? 'vendor');
    $binDir    = envValue('COMPOSER_BIN_DIR') ?? configString($config, 'bin-dir');

    $binDir = $binDir === null
        ? $vendorDir . DIRECTORY_SEPARATOR . 'bin'
        : composerPath($root, str_replace('{$vendor-dir}', $vendorDir, $binDir));

    // Proxy `phpunit` buatan Composer selalu ada tanpa ekstensi, juga di
    // Windows. PATH ikut diperiksa karena Composer menyuntik bin-dir ke PATH
    // saat menjalankan script.
    $candidates = [$binDir . DIRECTORY_SEPARATOR . 'phpunit'];

    foreach (pathDirectories() as $directory) {
        $candidates[] = rtrim($directory, '/\\') . DIRECTORY_SEPARATOR . 'phpunit';
    }

    // Jaring pengaman terakhir: lokasi asli binary di dalam paket.
    $candidates[] = $vendorDir . DIRECTORY_SEPARATOR . 'phpunit'
        . DIRECTORY_SEPARATOR . 'phpunit' . DIRECTORY_SEPARATOR . 'phpunit';

    foreach ($candidates as $candidate) {
        if (is_file($candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * @return array<string, mixed>
 */
function composerConfig(string $root): array
{
    $file = $root . DIRECTORY_SEPARATOR . 'composer.json';

    if (! is_file($file)) {
        return [];
    }

    $json = json_decode((string) file_get_contents($file), true);

    return is_array($json) && isset($json['config']) && is_array($json['config']) ? $json['config'] : [];
}

/**
 * Mengambil nilai config Composer yang berupa string tidak kosong.
 *
 * @param array<string, mixed> $config
 */
function configString(array $config, string $key): ?string
{
    $value = $config[$key] ?? null;

    return is_string($value) && trim($value) !== '' ? trim($value) : null;
}

/**
 * Mengubah path relatif dari composer.json menjadi path absolut.
 */
function composerPath(string $root, string $path): string
{
    $path = str_replace(['\\', '/'], DIRECTORY_SEPARATOR, trim($path));

    if (preg_match('~^(?:[A-Za-z]:[\\\\/]|[\\\\/])~', $path) === 1) {
        return $path;
    }

    return $root . DIRECTORY_SEPARATOR . $path;
}

/**
 * @return list<string>
 */
function pathDirectories(): array
{
    return array_values(array_filter(
        explode(PATH_SEPARATOR, (string) getenv('PATH')),
        static fn (string $directory): bool => $directory !== '',
    ));
}

function envValue(string $name): ?string
{
    $value = getenv($name);

    return $value === false || $value === '' ? null : $value;
}

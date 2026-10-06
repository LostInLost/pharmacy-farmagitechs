<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', static fn () => redirect()->to('/login'));

$routes->get('login', 'Web\AuthPages::login', ['filter' => 'guest']);

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->post('logout', 'Web\AuthPages::logout');
    $routes->get('receptions', 'Web\ReceptionPages::index');
    $routes->get('receptions/new', 'Web\ReceptionPages::form');
    $routes->get('receptions/(:num)/edit', 'Web\ReceptionPages::form/$1');
    $routes->get('stocks', 'Web\StockPages::index');
});

$routes->group('api', static function (RouteCollection $routes): void {
    // Preflight CORS: filter hanya berjalan bila rutenya terdaftar, jadi
    // OPTIONS harus ada. Filter `cors` menangani preflight dan membalas 204
    // sebelum closure ini dipanggil; closure hanya melayani OPTIONS biasa.
    $routes->options('(:any)', static function () {
        return service('response')
            ->setStatusCode(204)
            ->setHeader('Allow', 'GET, POST, PUT, OPTIONS');
    });

    // Bootstrap token CSRF untuk frontend lintas origin. Cookie CSRF bersifat
    // HttpOnly sehingga JavaScript tidak bisa membacanya; token dikirim lewat
    // header respons dan dibaca frontend (diekspos via CORS exposedHeaders).
    $routes->get('csrf', static function () {
        $security = service('security');

        return service('response')
            ->setStatusCode(200)
            ->setHeader('Cache-Control', 'no-store')
            ->setHeader($security->getHeaderName(), (string) $security->getHash())
            ->setJSON(['token' => $security->getHash()]);
    });

    $routes->post('login', 'Api\AuthController::login', ['filter' => 'guest']);

    $routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        $routes->post('logout', 'Api\AuthController::logout');

        $routes->get('receipts', 'Api\ReceptionController::index');
        $routes->post('receipts', 'Api\ReceptionController::create');
        $routes->get('receipts/(:num)', 'Api\ReceptionController::show/$1');
        $routes->put('receipts/(:num)', 'Api\ReceptionController::update/$1');

        $routes->get('stocks', 'Api\StockController::index');

        $routes->get('references/suppliers', 'Api\ReferenceController::suppliers');
        $routes->get('references/medicines', 'Api\ReferenceController::medicines');
    });
});

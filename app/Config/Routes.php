<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

$routes->get('/', static fn () => redirect()->to('/login'));

$routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
    $routes->get('logout', 'Web\AuthPages::logout');
    $routes->get('receptions', 'Web\ReceptionPages::index');
    $routes->get('receptions/new', 'Web\ReceptionPages::form');
    $routes->get('receptions/(:num)/edit', 'Web\ReceptionPages::form/$1');
    $routes->get('stocks', 'Web\StockPages::index');
});

$routes->get('login', 'Web\AuthPages::login');
$routes->post('login', 'Api\AuthController::login');

$routes->group('api', static function (RouteCollection $routes): void {
    $routes->post('login', 'Api\AuthController::login');

    $routes->group('', ['filter' => 'auth'], static function (RouteCollection $routes): void {
        $routes->post('logout', 'Api\AuthController::logout');

        $routes->get('receipts', 'Api\ReceptionController::index');
        $routes->post('receipts', 'Api\ReceptionController::create');
        $routes->get('receipts/(:num)', 'Api\ReceptionController::show/$1');
        $routes->put('receipts/(:num)', 'Api\ReceptionController::update/$1');

        $routes->get('stocks', 'Api\StockController::index');
    });
});

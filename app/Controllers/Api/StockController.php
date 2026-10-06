<?php

namespace App\Controllers\Api;

use App\Services\StockService;

class StockController extends BaseApiController
{
    private readonly StockService $stocks;

    public function __construct()
    {
        $this->stocks = new StockService();
    }

    public function index()
    {
        $onDate = $this->request->getGet('on_date');

        if (is_string($onDate) && trim($onDate) !== '' && ! $this->isValidDate($onDate)) {
            return $this->respondError('on_date harus berformat YYYY-MM-DD.', 422);
        }

        return $this->response->setStatusCode(200)->setJSON($this->stocks->report($onDate));
    }

    private function isValidDate(string $value): bool
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', trim($value));

        return $date !== false && $date->format('Y-m-d') === trim($value);
    }
}

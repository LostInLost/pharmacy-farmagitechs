<?php

namespace App\Services;

use App\Repositories\StockRepository;

class StockService
{
    public function __construct(
        private readonly StockRepository $stocks = new StockRepository(),
    ) {
    }
}

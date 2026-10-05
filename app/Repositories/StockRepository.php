<?php

namespace App\Repositories;

use App\Models\MedicineModel;
use App\Models\SeedBatchStockModel;
use App\Models\StockUsageModel;

class StockRepository
{
    public function __construct(
        private readonly MedicineModel $medicines = new MedicineModel(),
        private readonly SeedBatchStockModel $seedBatchStock = new SeedBatchStockModel(),
        private readonly StockUsageModel $stockUsage = new StockUsageModel(),
    ) {
    }
}

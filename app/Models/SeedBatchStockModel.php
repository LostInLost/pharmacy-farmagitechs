<?php

namespace App\Models;

use CodeIgniter\Model;

class SeedBatchStockModel extends Model
{
    protected $table         = 'seed_batch_stock';
    protected $primaryKey    = 'medicine_id';
    protected $returnType    = 'array';
    protected $allowedFields = [];
    protected $useTimestamps = false;
}

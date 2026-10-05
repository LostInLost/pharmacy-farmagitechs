<?php

namespace App\Models;

use CodeIgniter\Model;

class StockUsageModel extends Model
{
    protected $table         = 'stock_usage';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [];
    protected $useTimestamps = false;
}

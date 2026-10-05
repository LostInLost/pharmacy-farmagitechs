<?php

namespace App\Models;

use CodeIgniter\Model;

class MedicineModel extends Model
{
    protected $table         = 'medicines';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = [];
    protected $useTimestamps = false;
}

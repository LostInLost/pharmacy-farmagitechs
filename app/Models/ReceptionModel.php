<?php

namespace App\Models;

use CodeIgniter\Model;

class ReceptionModel extends Model
{
    protected $table         = 'receptions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['reference_no', 'supplier_id', 'received_at', 'created_by', 'created_at', 'updated_by', 'updated_at'];
    protected $useTimestamps = false;
}

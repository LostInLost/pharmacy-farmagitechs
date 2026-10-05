<?php

namespace App\Models;

use CodeIgniter\Model;

class ReceptionItemModel extends Model
{
    protected $table         = 'reception_items';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['reception_id', 'medicine_id', 'batch_no', 'expires_on', 'quantity'];
    protected $useTimestamps = false;
}

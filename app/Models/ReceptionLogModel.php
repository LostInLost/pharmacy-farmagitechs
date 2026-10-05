<?php

namespace App\Models;

use CodeIgniter\Model;

class ReceptionLogModel extends Model
{
    protected $table         = 'reception_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['reception_id', 'actor_id', 'action', 'created_at'];
    protected $useTimestamps = false;
}

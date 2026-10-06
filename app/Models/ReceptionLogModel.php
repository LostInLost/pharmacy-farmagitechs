<?php

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

class ReceptionLogModel extends Model
{
    protected $table         = 'reception_logs';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['reception_id', 'actor_id', 'action', 'created_at'];
    protected $useTimestamps = false;

    protected $beforeInsert      = ['stampCreated'];
    protected $beforeInsertBatch = ['stampCreatedBatch'];

    protected function stampCreated(array $eventData): array
    {
        $eventData['data']['created_at'] ??= Time::now()->toDateTimeString();

        return $eventData;
    }

    protected function stampCreatedBatch(array $eventData): array
    {
        $now = Time::now()->toDateTimeString();

        foreach ($eventData['data'] as $index => $row) {
            $eventData['data'][$index]['created_at'] ??= $now;
        }

        return $eventData;
    }
}

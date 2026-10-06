<?php

namespace App\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

class ReceptionModel extends Model
{
    protected $table         = 'receptions';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['reference_no', 'supplier_id', 'received_at', 'created_by', 'created_at', 'updated_by', 'updated_at'];
    protected $useTimestamps = false;

    protected $beforeInsert      = ['stampCreated'];
    protected $beforeInsertBatch = ['stampCreatedBatch'];
    protected $beforeUpdate      = ['stampUpdated'];

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

    protected function stampUpdated(array $eventData): array
    {
        $eventData['data']['updated_at'] = Time::now()->toDateTimeString();

        return $eventData;
    }
}

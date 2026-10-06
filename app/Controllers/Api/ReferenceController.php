<?php

namespace App\Controllers\Api;

use App\Models\MedicineModel;
use App\Models\SupplierModel;

class ReferenceController extends BaseApiController
{
    public function suppliers()
    {
        $rows = (new SupplierModel())
            ->select('id, name')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        return $this->withFreshCsrf($this->response->setStatusCode(200)->setJSON([
            'data' => array_map(static fn (array $row): array => [
                'id'   => (int) $row['id'],
                'name' => $row['name'],
            ], $rows),
        ]));
    }

    public function medicines()
    {
        $rows = (new MedicineModel())
            ->select('id, name, unit')
            ->where('is_active', 1)
            ->orderBy('name', 'ASC')
            ->findAll();

        return $this->withFreshCsrf($this->response->setStatusCode(200)->setJSON([
            'data' => array_map(static fn (array $row): array => [
                'id'   => (int) $row['id'],
                'name' => $row['name'],
                'unit' => $row['unit'],
            ], $rows),
        ]));
    }
}

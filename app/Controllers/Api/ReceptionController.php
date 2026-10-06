<?php

namespace App\Controllers\Api;

use App\Services\ReceptionService;

class ReceptionController extends BaseApiController
{
    private readonly ReceptionService $receptions;

    public function __construct()
    {
        $this->receptions = new ReceptionService();
    }

    public function index()
    {
        return $this->response->setStatusCode(200)->setJSON([
            'data' => $this->receptions->list(),
        ]);
    }

    public function show($id = null)
    {
        $reception = $this->receptions->detail((int) $id);

        if ($reception === null) {
            return $this->respondError('Penerimaan tidak ditemukan.', 404);
        }

        return $this->response->setStatusCode(200)->setJSON(['data' => $reception]);
    }

    public function create()
    {
        $payload = $this->request->getJSON(true) ?? [];
        $actorId = (int) session()->get('user_id');

        $result = $this->receptions->create($payload, $actorId);

        if (! $result['ok']) {
            return $this->respondError('Validasi gagal.', $result['status'], ['errors' => $result['errors']]);
        }

        return $this->response->setStatusCode(201)->setJSON([
            'message' => 'Penerimaan dibuat.',
            'data'    => $this->receptions->detail($result['id']),
        ]);
    }

    public function update($id = null)
    {
        $payload = $this->request->getJSON(true) ?? [];
        $actor   = [
            'id'   => (int) session()->get('user_id'),
            'role' => (string) session()->get('role'),
        ];

        $result = $this->receptions->update((int) $id, $payload, $actor);

        if (! $result['ok']) {
            return $this->respondError($result['errors'][0] ?? 'Gagal.', $result['status'], ['errors' => $result['errors']]);
        }

        return $this->response->setStatusCode(200)->setJSON([
            'message' => 'Penerimaan diperbarui.',
            'data'    => $this->receptions->detail((int) $id),
        ]);
    }
}

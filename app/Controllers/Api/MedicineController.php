<?php

namespace App\Controllers\Api;

use App\Policies\MedicinePolicy;
use App\Services\MedicineService;

class MedicineController extends BaseApiController
{
    private readonly MedicineService $medicines;
    private readonly MedicinePolicy $policy;

    public function __construct()
    {
        $this->medicines = new MedicineService();
        $this->policy    = new MedicinePolicy();
    }

    public function index()
    {
        $actor = $this->actor();

        if (! $this->policy->canView($actor)) {
            return $this->respondError(lang('Medicine.api.forbidden'), 403);
        }

        $q      = $this->request->getGet('q');
        $status = $this->request->getGet('status');

        if (is_string($status) && ! in_array($status, ['all', 'active', 'inactive'], true)) {
            return $this->respondError(lang('Medicine.api.invalid_status'), 422);
        }

        return $this->withFreshCsrf($this->response->setStatusCode(200)->setJSON([
            'data'      => $this->medicines->list(is_string($q) ? $q : null, is_string($status) ? $status : 'all'),
            'can_write' => $this->policy->canWrite($actor),
        ]));
    }

    public function show($id = null)
    {
        $actor = $this->actor();

        if (! $this->policy->canView($actor)) {
            return $this->respondError(lang('Medicine.api.forbidden'), 403);
        }

        $medicine = $this->medicines->detail((int) $id);

        if ($medicine === null) {
            return $this->respondError(lang('Medicine.api.not_found'), 404);
        }

        return $this->withFreshCsrf($this->response->setStatusCode(200)->setJSON([
            'data'      => $medicine,
            'can_write' => $this->policy->canWrite($actor),
        ]));
    }

    public function create()
    {
        $payload = $this->request->getJSON(true) ?? [];

        $result = $this->medicines->create($payload, $this->actor());

        if (! $result['ok']) {
            return $this->respondError($result['errors'][0] ?? lang('Medicine.api.failed'), $result['status'], ['errors' => $result['errors']]);
        }

        return $this->withFreshCsrf($this->response->setStatusCode(201)->setJSON([
            'message' => lang('Medicine.api.created'),
            'data'    => $this->medicines->detail($result['id']),
        ]));
    }

    public function update($id = null)
    {
        $payload = $this->request->getJSON(true) ?? [];

        $result = $this->medicines->update((int) $id, $payload, $this->actor());

        if (! $result['ok']) {
            return $this->respondError($result['errors'][0] ?? lang('Medicine.api.failed'), $result['status'], ['errors' => $result['errors']]);
        }

        return $this->withFreshCsrf($this->response->setStatusCode(200)->setJSON([
            'message' => lang('Medicine.api.updated'),
            'data'    => $this->medicines->detail((int) $id),
        ]));
    }

    private function actor(): array
    {
        return [
            'id'   => (int) session()->get('user_id'),
            'role' => (string) session()->get('role'),
        ];
    }
}

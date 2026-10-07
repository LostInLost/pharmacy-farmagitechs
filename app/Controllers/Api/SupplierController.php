<?php

namespace App\Controllers\Api;

use App\Policies\SupplierPolicy;
use App\Services\SupplierService;

class SupplierController extends BaseApiController
{
    private readonly SupplierService $suppliers;
    private readonly SupplierPolicy $policy;

    public function __construct()
    {
        $this->suppliers = new SupplierService();
        $this->policy    = new SupplierPolicy();
    }

    public function index()
    {
        $actor = $this->actor();

        if (! $this->policy->canView($actor)) {
            return $this->respondError(lang('Supplier.api.forbidden'), 403);
        }

        $q      = $this->request->getGet('q');
        $status = $this->request->getGet('status');

        if (is_string($status) && ! in_array($status, ['all', 'active', 'inactive'], true)) {
            return $this->respondError(lang('Supplier.api.invalid_status'), 422);
        }

        return $this->withFreshCsrf($this->response->setStatusCode(200)->setJSON([
            'data'      => $this->suppliers->list(is_string($q) ? $q : null, is_string($status) ? $status : 'all'),
            'can_write' => $this->policy->canWrite($actor),
        ]));
    }

    public function show($id = null)
    {
        $actor = $this->actor();

        if (! $this->policy->canView($actor)) {
            return $this->respondError(lang('Supplier.api.forbidden'), 403);
        }

        $supplier = $this->suppliers->detail((int) $id);

        if ($supplier === null) {
            return $this->respondError(lang('Supplier.api.not_found'), 404);
        }

        return $this->withFreshCsrf($this->response->setStatusCode(200)->setJSON([
            'data'      => $supplier,
            'can_write' => $this->policy->canWrite($actor),
        ]));
    }

    public function create()
    {
        $payload = $this->request->getJSON(true) ?? [];

        $result = $this->suppliers->create($payload, $this->actor());

        if (! $result['ok']) {
            return $this->respondError($result['errors'][0] ?? lang('Supplier.api.failed'), $result['status'], ['errors' => $result['errors']]);
        }

        return $this->withFreshCsrf($this->response->setStatusCode(201)->setJSON([
            'message' => lang('Supplier.api.created'),
            'data'    => $this->suppliers->detail($result['id']),
        ]));
    }

    public function update($id = null)
    {
        $payload = $this->request->getJSON(true) ?? [];

        $result = $this->suppliers->update((int) $id, $payload, $this->actor());

        if (! $result['ok']) {
            return $this->respondError($result['errors'][0] ?? lang('Supplier.api.failed'), $result['status'], ['errors' => $result['errors']]);
        }

        return $this->withFreshCsrf($this->response->setStatusCode(200)->setJSON([
            'message' => lang('Supplier.api.updated'),
            'data'    => $this->suppliers->detail((int) $id),
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

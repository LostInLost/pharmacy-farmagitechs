<?php

namespace App\Controllers\Api;

use App\Policies\ReceptionPolicy;
use App\Services\ReceptionService;

class ReceptionController extends BaseApiController
{
    private readonly ReceptionService $receptions;
    private readonly ReceptionPolicy $policy;

    public function __construct()
    {
        $this->receptions = new ReceptionService();
        $this->policy     = new ReceptionPolicy();
    }

    public function index()
    {
        $actor = $this->actor();

        $rows = array_map(
            fn (array $reception): array => $reception + ['can_update' => $this->policy->canUpdate($actor, $reception)],
            $this->receptions->list(),
        );

        return $this->withFreshCsrf($this->response->setStatusCode(200)->setJSON([
            'data' => $rows,
        ]));
    }

    public function show($id = null)
    {
        $reception = $this->receptions->detail((int) $id);

        if ($reception === null) {
            return $this->respondError(lang('Reception.api.not_found'), 404);
        }

        $reception['can_update'] = $this->policy->canUpdate($this->actor(), $reception);

        return $this->withFreshCsrf($this->response->setStatusCode(200)->setJSON(['data' => $reception]));
    }

    public function create()
    {
        $payload = $this->request->getJSON(true) ?? [];
        $actorId = (int) session()->get('user_id');

        $result = $this->receptions->create($payload, $actorId);

        if (! $result['ok']) {
            return $this->respondError(lang('Reception.api.validation_failed'), $result['status'], ['errors' => $result['errors']]);
        }

        return $this->withFreshCsrf($this->response->setStatusCode(201)->setJSON([
            'message' => lang('Reception.api.created'),
            'data'    => $this->receptions->detail($result['id']),
        ]));
    }

    public function update($id = null)
    {
        $payload = $this->request->getJSON(true) ?? [];
        $actor   = $this->actor();

        $result = $this->receptions->update((int) $id, $payload, $actor);

        if (! $result['ok']) {
            return $this->respondError($result['errors'][0] ?? lang('Reception.api.failed'), $result['status'], ['errors' => $result['errors']]);
        }

        return $this->withFreshCsrf($this->response->setStatusCode(200)->setJSON([
            'message' => lang('Reception.api.updated'),
            'data'    => $this->receptions->detail((int) $id),
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

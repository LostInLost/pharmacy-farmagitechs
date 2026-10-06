<?php

namespace App\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BaseApiController extends Controller
{
    protected function respondError(string $message, int $status = 422, array $extra = []): ResponseInterface
    {
        return $this->response
            ->setStatusCode($status)
            ->setJSON(['message' => $message] + $extra);
    }

    protected function notImplemented()
    {
        return $this->respondError('Not implemented yet.', 501);
    }
}

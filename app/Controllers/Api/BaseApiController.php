<?php

namespace App\Controllers\Api;

use CodeIgniter\Controller;

abstract class BaseApiController extends Controller
{
    protected function notImplemented()
    {
        return $this->response
            ->setStatusCode(501)
            ->setJSON(['message' => 'Not implemented yet.']);
    }
}

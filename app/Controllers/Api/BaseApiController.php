<?php

namespace App\Controllers\Api;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BaseApiController extends Controller
{
    protected function respondError(string $message, int $status = 422, array $extra = []): ResponseInterface
    {
        return $this->withFreshCsrf(
            $this->response->setStatusCode($status)->setJSON(['message' => $message] + $extra),
        );
    }

    /**
     * Menyisipkan token CSRF terbaru pada response.
     *
     * Token berotasi setiap kali permintaan mutasi berhasil (regenerate=true),
     * jadi klien harus selalu memakai nilai dari response terakhir.
     */
    protected function withFreshCsrf(ResponseInterface $response): ResponseInterface
    {
        return $response->setHeader(service('security')->getHeaderName(), csrf_hash());
    }

    protected function notImplemented()
    {
        return $this->respondError(lang('App.api.not_implemented'), 501);
    }
}

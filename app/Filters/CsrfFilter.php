<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Security\Security;

/**
 * Menggantikan filter CSRF bawaan agar kegagalan token dijawab JSON 403
 * untuk /api/* dan redirect kembali ke halaman asal untuk web.
 */
class CsrfFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! $request instanceof IncomingRequest) {
            return null;
        }

        /** @var Security $security */
        $security = service('security');

        try {
            $security->verify($request);
        } catch (SecurityException) {
            return $this->reject($request, $security);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }

    private function reject(RequestInterface $request, Security $security): ResponseInterface
    {
        if (str_starts_with($this->path($request), '/api/')) {
            return service('response')
                ->setStatusCode(403)
                ->setHeader($security->getHeaderName(), (string) $security->getHash())
                ->setJSON(['message' => lang('App.csrf.failed'), 'error' => 'csrf']);
        }

        return redirect()->back()->with('error', lang('App.csrf.failed'));
    }

    private function path(RequestInterface $request): string
    {
        return preg_replace('#^/index\.php#', '', '/' . ltrim($request->getUri()->getPath(), '/'));
    }
}

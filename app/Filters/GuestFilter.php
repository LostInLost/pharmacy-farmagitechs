<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Kebalikan AuthFilter: rute khusus tamu (login) menolak pengguna
 * yang sudah login dengan error 403 eksplisit, bukan redirect diam-diam.
 */
class GuestFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('user_id') === null) {
            return null;
        }

        $path = preg_replace('#^/index\.php#', '', '/' . ltrim($request->getUri()->getPath(), '/'));

        if (str_starts_with($path, '/api/')) {
            // Token CSRF sudah berotasi di filter global `csrf`; response
            // penolakan tetap harus membawa nilai terbaru agar klien API
            // tidak terjebak 403 csrf pada request berikutnya.
            $security = service('security');

            return service('response')
                ->setStatusCode(403)
                ->setHeader($security->getHeaderName(), (string) $security->getHash())
                ->setJSON(['message' => lang('Auth.already_authenticated')]);
        }

        return service('response')
            ->setStatusCode(403)
            ->setBody(view('auth/already_authenticated', [
                'title' => lang('Auth.already.title'),
            ]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}

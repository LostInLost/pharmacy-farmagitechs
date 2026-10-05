<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session()->get('user_id') !== null) {
            return null;
        }

        $path = preg_replace('#^/index\.php#', '', '/' . ltrim($request->getUri()->getPath(), '/'));

        if (str_starts_with($path, '/api/')) {
            return service('response')
                ->setStatusCode(401)
                ->setJSON(['message' => 'Unauthenticated.']);
        }

        return redirect()->to('/login');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}

<?php

namespace App\Controllers\Api;

use App\Services\AuthService;

class AuthController extends BaseApiController
{
    private readonly AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function login()
    {
        $payload = $this->request->getJSON(true) ?? [];

        $username = trim((string) ($payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($username === '' || $password === '') {
            return $this->respondError('Username dan kata sandi wajib diisi.', 422);
        }

        $user = $this->auth->attempt($username, $password);

        if ($user === null) {
            return $this->respondError('Kredensial tidak valid.', 401);
        }

        $this->auth->login($user);

        return $this->response->setStatusCode(200)->setJSON([
            'message' => 'Login berhasil.',
            'user'    => $user,
        ]);
    }

    public function logout()
    {
        $this->auth->logout();

        return $this->response->setStatusCode(200)->setJSON(['message' => 'Logout berhasil.']);
    }
}

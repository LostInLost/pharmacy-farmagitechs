<?php

namespace App\Controllers\Api;

use App\Services\AuthService;
use Config\Permissions;

class AuthController extends BaseApiController
{
    private readonly AuthService $auth;
    private readonly Permissions $permissions;

    public function __construct()
    {
        $this->auth        = new AuthService();
        $this->permissions = new Permissions();
    }

    public function login()
    {
        $payload = $this->request->getJSON(true) ?? [];

        $username = trim((string) ($payload['username'] ?? ''));
        $password = (string) ($payload['password'] ?? '');

        if ($username === '' || $password === '') {
            return $this->respondError(lang('Auth.api.required'), 422);
        }

        $user = $this->auth->attempt($username, $password);

        if ($user === null) {
            return $this->respondError(lang('Auth.api.invalid'), 401);
        }

        $this->auth->login($user);

        return $this->withFreshCsrf($this->response->setStatusCode(200)->setJSON([
            'message' => lang('Auth.api.success'),
            'user'    => $this->withPermissions($user),
        ]));
    }

    public function logout()
    {
        $this->auth->logout();

        return $this->withFreshCsrf(
            $this->response->setStatusCode(200)->setJSON(['message' => lang('Auth.api.logout')]),
        );
    }

    /**
     * Cek sesi untuk middleware SSR frontend Astro. GET aman dari CSRF
     * (Security::verify melewatkan metode aman), jadi bisa dipanggil
     * server-side dengan meneruskan header Cookie.
     */
    public function me()
    {
        $userId = session()->get('user_id');

        if ($userId === null) {
            return $this->respondError(lang('Auth.unauthenticated'), 401);
        }

        return $this->withFreshCsrf($this->response->setStatusCode(200)->setJSON([
            'user' => [
                'id'          => (int) $userId,
                'name'        => (string) session()->get('user_name'),
                'username'    => (string) (session()->get('username') ?? ''),
                'role'        => (string) session()->get('role'),
                'permissions' => $this->permissions->forRole((string) session()->get('role')),
            ],
        ]));
    }

    /**
     * Sertakan permission role pada payload user agar UI bisa menyembunyikan
     * aksi yang tak mungkin diizinkan (mis. tombol tambah bagi role tanpa
     * `receipt.create`) tanpa perlu memanggil endpoint terpisah.
     *
     * Ini murni affordance: penegakan tetap di policy server per request.
     * Permission tidak disimpan di session supaya perubahan `Config\Permissions`
     * langsung berlaku pada request berikutnya, bukan setelah login ulang.
     */
    private function withPermissions(array $user): array
    {
        $user['permissions'] = $this->permissions->forRole((string) ($user['role'] ?? ''));

        return $user;
    }
}

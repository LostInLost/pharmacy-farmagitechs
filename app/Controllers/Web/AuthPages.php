<?php

namespace App\Controllers\Web;

use App\Services\AuthService;
use CodeIgniter\Controller;

class AuthPages extends Controller
{
    private readonly AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    public function login()
    {
        if (session()->get('user_id') !== null) {
            return redirect()->to('/receptions');
        }

        return view('auth/login', ['error' => session()->getFlashdata('error')]);
    }

    public function attempt()
    {
        $username = trim((string) $this->request->getPost('username'));
        $password = (string) $this->request->getPost('password');

        $user = $this->auth->attempt($username, $password);

        if ($user === null) {
            return redirect()->back()->withInput()->with('error', 'Username atau kata sandi salah.');
        }

        $this->auth->login($user);

        return redirect()->to('/receptions');
    }

    public function logout()
    {
        $this->auth->logout();

        return redirect()->to('/login');
    }
}

<?php

namespace App\Controllers\Web;

use App\Services\AuthService;
use CodeIgniter\Controller;

class AuthPages extends Controller
{
    public function login()
    {
        return view('auth/login', ['title' => lang('Auth.login.title')]);
    }

    public function logout()
    {
        (new AuthService())->logout();

        return redirect()->to('/login');
    }
}

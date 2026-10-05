<?php

namespace App\Controllers\Web;

use CodeIgniter\Controller;

class AuthPages extends Controller
{
    public function login()
    {
        return view('auth/login');
    }

    public function logout()
    {
        return redirect()->to('/login');
    }
}

<?php

namespace App\Controllers\Web;

use CodeIgniter\Controller;

class ReceptionPages extends Controller
{
    public function index()
    {
        return view('receptions/index');
    }

    public function form($id = null)
    {
        return view('receptions/form');
    }
}

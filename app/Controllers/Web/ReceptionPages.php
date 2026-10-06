<?php

namespace App\Controllers\Web;

use CodeIgniter\Controller;

class ReceptionPages extends Controller
{
    public function index()
    {
        return view('receptions/index', [
            'title' => lang('Reception.title.list'),
        ]);
    }

    public function form($id = null)
    {
        return view('receptions/form', [
            'title'       => $id === null ? lang('Reception.title.new') : lang('Reception.title.edit'),
            'receptionId' => $id === null ? null : (int) $id,
        ]);
    }
}

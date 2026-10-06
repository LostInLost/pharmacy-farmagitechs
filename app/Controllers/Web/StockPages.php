<?php

namespace App\Controllers\Web;

use CodeIgniter\Controller;

class StockPages extends Controller
{
    public function index()
    {
        return view('stocks/index', [
            'title' => lang('Stock.title'),
        ]);
    }
}

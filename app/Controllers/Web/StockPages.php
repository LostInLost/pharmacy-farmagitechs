<?php

namespace App\Controllers\Web;

use App\Services\StockService;
use CodeIgniter\Controller;

class StockPages extends Controller
{
    public function index()
    {
        $onDate = $this->request->getGet('on_date');
        $report = (new StockService())->report(is_string($onDate) ? $onDate : null);

        return view('stocks/index', [
            'title'    => 'Laporan Stok',
            'onDate'   => $report['on_date'],
            'medicines' => $report['medicines'],
        ]);
    }
}

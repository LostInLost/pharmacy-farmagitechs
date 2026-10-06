<?php

namespace App\Controllers\Web;

use App\Models\MedicineModel;
use App\Models\SupplierModel;
use App\Policies\ReceptionPolicy;
use App\Services\ReceptionService;
use CodeIgniter\Controller;

class ReceptionPages extends Controller
{
    private readonly ReceptionService $receptions;

    public function __construct()
    {
        $this->receptions = new ReceptionService();
    }

    public function index()
    {
        $actor = [
            'id'   => (int) session()->get('user_id'),
            'role' => (string) session()->get('role'),
        ];

        return view('receptions/index', [
            'title'       => 'Penerimaan',
            'receptions'  => $this->receptions->list(),
            'actor'       => $actor,
            'permissions' => new \Config\Permissions(),
        ]);
    }

    public function form($id = null)
    {
        $actor = [
            'id'   => (int) session()->get('user_id'),
            'role' => (string) session()->get('role'),
        ];

        $reception = $id === null ? null : $this->receptions->detail((int) $id);

        if ($id !== null && $reception === null) {
            return redirect()->to('/receptions')->with('error', 'Penerimaan tidak ditemukan.');
        }

        if ($reception !== null && ! (new ReceptionPolicy())->canUpdate($actor, $reception)) {
            return redirect()->to('/receptions')->with('error', 'Anda tidak berhak mengubah penerimaan ini.');
        }

        return view('receptions/form', [
            'title'     => $id === null ? 'Penerimaan Baru' : 'Detail & Ubah Penerimaan',
            'reception' => $reception,
            'canEdit'   => true,
            'suppliers' => (new SupplierModel())->where('is_active', 1)->orderBy('name')->findAll(),
            'medicines' => (new MedicineModel())->where('is_active', 1)->orderBy('name')->findAll(),
        ]);
    }
}

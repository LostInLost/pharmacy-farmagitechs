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
            'title'       => lang('Reception.title.list'),
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
            return redirect()->to('/receptions')->with('error', lang('Reception.flash.not_found'));
        }

        if ($reception !== null && ! (new ReceptionPolicy())->canUpdate($actor, $reception)) {
            return redirect()->to('/receptions')->with('error', lang('Reception.flash.not_allowed'));
        }

        return view('receptions/form', [
            'title'     => $id === null ? lang('Reception.title.new') : lang('Reception.title.edit'),
            'reception' => $reception,
            'canEdit'   => true,
            'suppliers' => (new SupplierModel())->where('is_active', 1)->orderBy('name')->findAll(),
            'medicines' => (new MedicineModel())->where('is_active', 1)->orderBy('name')->findAll(),
        ]);
    }
}

<?php

namespace App\Controllers;

use App\Services\AuthorizationService;
use App\Services\SalesGoalService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

final class Goals extends BaseController
{
    public function index(): string|ResponseInterface
    {
        $a = new AuthorizationService();
        $p = (int)session('user.perfil_id');
        if (!$a->allows($p, 'meta', 'index')) return $this->response->setStatusCode(403)->setBody('Operacion no autorizada.');
        $y = (int)($this->request->getGet('anio') ?: date('Y'));
        if ($y < 2000 || $y > 2100) $y = (int)date('Y');
        return view('goals/index', ['title' => 'Metas | CRM', 'heading' => 'Metas ' . $y, 'breadcrumbs' => ['Inicio' => site_url('home'), 'Metas' => null], 'permissions' => session('permissions') ?? [], 'goals' => (new SalesGoalService())->annual($y, session('user') ?? []), 'canEdit' => $a->allows($p, 'meta', 'edit')]);
    }
    public function save(): ResponseInterface
    {
        $a = new AuthorizationService();
        $p = (int)session('user.perfil_id');
        if (!$a->allows($p, 'meta', 'edit')) return $this->response->setStatusCode(403)->setJSON(['ok' => false]);
        try {
            (new SalesGoalService())->saveBatch($this->request->getPost() ?: [], session('user') ?? []);
        } catch (InvalidArgumentException | RuntimeException $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
        return redirect()->to(site_url('meta?anio=' . (int)$this->request->getPost('anio')))->with('success', 'Metas guardadas.');
    }
}

<?php

namespace App\Controllers;

use App\Services\AuthorizationService;
use App\Services\DashboardService;
use CodeIgniter\HTTP\ResponseInterface;

class Home extends BaseController
{
    private AuthorizationService $authorization;
    private DashboardService $dashboard;

    public function __construct()
    {
        $this->authorization = new AuthorizationService();
        $this->dashboard = new DashboardService($this->authorization);
    }

    public function index(): string|ResponseInterface
    {
        if (! $this->authorization->allows((int) session('user.perfil_id'), 'dashboard', 'index')) {
            return $this->response->setStatusCode(403)->setBody('Operacion no autorizada.');
        }

        return view('dashboard/index', [
            'title' => 'Dashboard | CRM',
            'heading' => 'Dashboard',
            'breadcrumbs' => ['Inicio' => null],
            'permissions' => session('permissions') ?? ['dashboard.index'],
            'summary' => $this->dashboard->summary(session('user') ?? [], (int) ($this->request->getGet('anio') ?: date('Y'))),
        ]);
    }
}

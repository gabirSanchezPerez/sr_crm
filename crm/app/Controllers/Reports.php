<?php

namespace App\Controllers;

use App\Services\AuthorizationService;
use App\Services\ReportExportService;
use CodeIgniter\HTTP\ResponseInterface;

final class Reports extends BaseController
{
    private AuthorizationService $authorization;
    private ReportExportService $exports;

    public function __construct()
    {
        $this->authorization = new AuthorizationService();
        $this->exports = new ReportExportService();
    }

    public function followUp(): string|ResponseInterface
    {
        return $this->screen('followUp');
    }

    public function wallet(): string|ResponseInterface
    {
        return $this->screen('wallet');
    }

    public function followUpExport(): ResponseInterface
    {
        return $this->downloadExport('followUp');
    }

    public function walletExport(): ResponseInterface
    {
        return $this->downloadExport('wallet');
    }

    private function screen(string $report): string|ResponseInterface
    {
        if (! $this->canView()) {
            return $this->forbidden();
        }

        $filters = $this->filters();
        $data = $report === 'wallet'
            ? $this->exports->walletScreen($this->identity(), $filters)
            : $this->exports->followUpScreen($this->identity(), $filters);

        return view('reports/screen', [
            'title' => ($report === 'wallet' ? 'Reporte cartera' : 'Reporte seguimiento') . ' | CRM',
            'heading' => $report === 'wallet' ? 'Reporte cartera' : 'Reporte seguimiento',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Reportes' => null],
            'permissions' => session('permissions') ?? [],
            'report' => $report,
            'filters' => $filters,
            'options' => $this->exports->filterOptions($this->identity()),
            'rows' => $data['rows'],
            'totals' => $data['totals'],
            'exportUrl' => site_url($report === 'wallet' ? 'reporte/cartera/export' : 'reporte/seguimiento/export'),
        ]);
    }

    private function downloadExport(string $report): ResponseInterface
    {
        if (! $this->canView()) {
            return $this->forbidden();
        }

        $export = $report === 'wallet'
            ? $this->exports->exportWallet($this->identity(), $this->request->getGet())
            : $this->exports->exportFollowUps($this->identity(), $this->request->getGet());

        return $this->response
            ->download($export['path'], null)
            ->setFileName($export['filename'])
            ->setContentType('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', '');
    }

    /** @return array<string, mixed> */
    private function filters(): array
    {
        return [
            'u' => (string) ($this->request->getGetPost('u') ?? '-1'),
            'e' => (string) ($this->request->getGetPost('e') ?? '-1'),
            't' => (string) ($this->request->getGetPost('t') ?? '-1'),
        ];
    }

    private function canView(): bool
    {
        return $this->authorization->allows((int) session('user.perfil_id'), 'reporte', 'index');
    }

    /** @return array<string, mixed> */
    private function identity(): array
    {
        return session('user') ?? [];
    }

    private function forbidden(): ResponseInterface
    {
        return $this->response->setStatusCode(403)->setBody('Operacion no autorizada.');
    }
}
<?php

namespace App\Controllers;

use App\Services\AuthorizationService;
use CodeIgniter\HTTP\ResponseInterface;

final class Compatibility extends BaseController
{
    private AuthorizationService $authorization;

    public function __construct()
    {
        $this->authorization = new AuthorizationService();
    }

    public function search(): string|ResponseInterface
    {
        if (! $this->canSearch()) {
            return $this->forbidden();
        }

        return view('compatibility/search', [
            'title' => 'Busqueda | CRM',
            'heading' => 'Busqueda',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Busqueda' => null],
            'permissions' => session('permissions') ?? [],
            'types' => ['-1' => 'Todos', '1' => 'Anunciantes', '2' => 'Potenciales'],
        ]);
    }

    public function searchClient(): ResponseInterface
    {
        if (! $this->canSearch()) {
            return $this->response->setStatusCode(403)->setJSON(['data' => []]);
        }

        $identity = $this->identity();
        $type = (string) ($this->request->getPost('t') ?? '-1');
        $rows = [];
        $total = 0;

        if ($type !== '2' && $this->authorization->allows((int) $identity['perfil_id'], 'cliente', 'index')) {
            $customerRows = $this->accountSearchRows('cliente', 'Anunciante', $identity, $this->authorization->scope((int) $identity['perfil_id'], 'cliente'));
            $rows = array_merge($rows, $customerRows);
            $total += $this->accountTotal('cliente', $identity, $this->authorization->scope((int) $identity['perfil_id'], 'cliente'));
        }
        if ($type !== '1' && $this->authorization->allows((int) $identity['perfil_id'], 'cpotencial', 'index')) {
            $prospectRows = $this->accountSearchRows('cpotencial', 'Potencial', $identity, $this->authorization->scope((int) $identity['perfil_id'], 'cpotencial'));
            $rows = array_merge($rows, $prospectRows);
            $total += $this->accountTotal('cpotencial', $identity, $this->authorization->scope((int) $identity['perfil_id'], 'cpotencial'));
        }

        $start = max(0, (int) ($this->request->getPost('start') ?? 0));
        $length = (int) ($this->request->getPost('length') ?? 10);
        $paged = $length > 0 ? array_slice($rows, $start, $length) : $rows;

        return $this->response->setJSON([
            'draw' => (int) ($this->request->getPost('draw') ?? 0),
            'recordsTotal' => $total,
            'recordsFiltered' => count($rows),
            'data' => array_values($paged),
        ]);
    }

    public function customerExists(): ResponseInterface
    {
        if (! $this->authorization->allows((int) session('user.perfil_id'), 'cliente', 'index')) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }

        return $this->response->setContentType('application/json')->setBody((string) $this->duplicateCount('cliente'));
    }

    public function prospectExists(): ResponseInterface
    {
        if (! $this->authorization->allows((int) session('user.perfil_id'), 'cpotencial', 'index')) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }

        return $this->response->setContentType('application/json')->setBody((string) $this->duplicateCount('cpotencial'));
    }

    /** @return list<array<string,string>> */
    private function accountSearchRows(string $table, string $label, array $identity, string $scope): array
    {
        $alias = $table === 'cliente' ? 'c' : 'cp';
        $builder = db_connect()->table($table . ' ' . $alias)
            ->select($alias . '.razon_social, ' . $alias . '.marca, u.nombre AS usuario')
            ->join('usuario u', 'u.id = ' . $alias . '.ejecutivo_id AND u.deleted = 0', 'inner')
            ->join('usuario_ucomercial uuc', 'uuc.usuario_id = u.id AND uuc.deleted = 0', 'left')
            ->where($alias . '.deleted', 0)
            ->orderBy($alias . '.razon_social', 'ASC')
            ->orderBy($alias . '.marca', 'ASC');

        if ($table === 'cpotencial') {
            $builder->where($alias . '.cliente_id', null);
        }

        $this->applyAccountScope($builder, $alias, $identity, $scope);
        $this->applySearchFilters($builder, $alias);

        $rows = [];
        foreach ($builder->get()->getResultArray() as $row) {
            $rows[] = [
                'razon_social' => esc((string) $row['razon_social']),
                'marca' => esc((string) $row['marca']),
                'tipo' => '<span class="badge ' . ($label === 'Anunciante' ? 'badge-success' : 'badge-primary') . '">' . esc($label) . '</span>',
                'usuario' => esc((string) $row['usuario']),
            ];
        }

        return $rows;
    }

    private function accountTotal(string $table, array $identity, string $scope): int
    {
        $alias = $table === 'cliente' ? 'c' : 'cp';
        $builder = db_connect()->table($table . ' ' . $alias)
            ->join('usuario_ucomercial uuc', 'uuc.usuario_id = ' . $alias . '.ejecutivo_id AND uuc.deleted = 0', 'left')
            ->where($alias . '.deleted', 0);

        if ($table === 'cpotencial') {
            $builder->where($alias . '.cliente_id', null);
        }

        $this->applyAccountScope($builder, $alias, $identity, $scope);

        return $builder->countAllResults();
    }

    private function duplicateCount(string $table): int
    {
        $businessName = trim((string) ($this->request->getPost('razon') ?? $this->request->getPost('razon_social') ?? ''));
        $brand = trim((string) ($this->request->getPost('marca') ?? ''));
        $executiveId = (int) ($this->request->getPost('ejecutivo') ?? $this->request->getPost('ejecutivo_id') ?? 0);

        if ($businessName === '' || $brand === '' || $executiveId <= 0) {
            return 0;
        }

        $db = db_connect();
        $accountTable = $db->escapeIdentifiers($db->prefixTable($table));
        $userTable = $db->escapeIdentifiers($db->prefixTable('usuario'));
        $sql = 'SELECT COUNT(*) AS total FROM ' . $accountTable . ' acct '
            . 'INNER JOIN ' . $userTable . ' u ON acct.ejecutivo_id = u.id '
            . 'WHERE acct.deleted = 0 AND acct.razon_social = ? AND acct.marca = ? AND u.cgestion_id = ?';
        $params = [$businessName, $brand, $this->managementIdForExecutive($executiveId)];
        if ($table === 'cpotencial') {
            $sql .= ' AND acct.cliente_id IS NULL';
        }

        return (int) ($db->query($sql, $params)->getRowArray()['total'] ?? 0);
    }

    private function managementIdForExecutive(int $executiveId): int
    {
        $row = db_connect()->table('usuario')->select('cgestion_id')->where('id', $executiveId)->where('deleted', 0)->get()->getRowArray();

        return (int) ($row['cgestion_id'] ?? 0);
    }

    private function applySearchFilters(object $builder, string $alias): void
    {
        $businessName = trim((string) ($this->request->getPost('r') ?? ''));
        $brand = trim((string) ($this->request->getPost('m') ?? ''));
        if ($businessName !== '') {
            $builder->like($alias . '.razon_social', $businessName);
        }
        if ($brand !== '') {
            $builder->like($alias . '.marca', $brand);
        }
    }

    private function applyAccountScope(object $builder, string $alias, array $identity, string $scope): void
    {
        if ($scope === 'owner') {
            $builder->where($alias . '.ejecutivo_id', (int) ($identity['user_id'] ?? 0));
            return;
        }
        if ($scope === 'team') {
            $builder->where('uuc.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0));
            return;
        }
        if ($scope !== 'all') {
            $builder->where('1 =', 0, false);
        }
    }

    private function canSearch(): bool
    {
        $profileId = (int) session('user.perfil_id');

        return $this->authorization->allows($profileId, 'cliente', 'index') || $this->authorization->allows($profileId, 'cpotencial', 'index');
    }

    /** @return array<string,mixed> */
    private function identity(): array
    {
        return session('user') ?? [];
    }

    private function forbidden(): ResponseInterface
    {
        return $this->response->setStatusCode(403)->setBody('Operacion no autorizada.');
    }
}
<?php

namespace App\Controllers;

use App\Support\DataTableResponder;
use App\Services\AuthorizationService;
use App\Services\DocumentService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

final class Documents extends BaseController
{
    private AuthorizationService $authorization;
    private DocumentService $documents;

    public function __construct()
    {
        $this->authorization = new AuthorizationService();
        $this->documents = new DocumentService();
    }

    public function index(): string|ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->forbidden();
        }
        return view('documents/index', [
            'title' => 'Documentos | CRM',
            'heading' => 'Documentos',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Documentos' => null],
            'permissions' => session('permissions') ?? [],
            'documents' => $this->documents->rows($this->identity(), $this->scope(), $this->request->getGet('q')),
            'canDeleteDocument' => $this->can('delete'),
            'parentType' => '',
            'parentId' => 0,
        ]);
    }

    public function rows(): ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->response->setStatusCode(403)->setJSON(['data' => []]);
        }
        $dataTable = new DataTableResponder();
        $totalRows = $this->documents->rows($this->identity(), $this->scope());
        $filteredRows = $this->documents->rows($this->identity(), $this->scope(), $dataTable->search($this->request));

        return $this->response->setJSON($dataTable->payload(
            $this->request,
            $totalRows,
            $filteredRows,
            fn (array $row): array => $row + ['_actions' => $this->rowActions($row)],
            ['nombre', 'archivo_original', 'cliente', 'cpotencial']
        ));
    }

    public function subpanel(): string|ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->forbidden();
        }
        return view('documents/subpanel', [
            'documents' => [],
            'parentType' => (string) $this->request->getGet('module'),
            'parentId' => (int) $this->request->getGet('father_id'),
            'canAddDocument' => $this->can('add'),
            'canDeleteDocument' => $this->can('delete'),
        ]);
    }

    public function subpanelRows(): ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->response->setStatusCode(403)->setJSON(['data' => []]);
        }
        $parentType = (string) $this->request->getPost('module');
        $parentId = (int) $this->request->getPost('father_id');
        $dataTable = new DataTableResponder();
        $totalRows = $this->documents->forParent($parentType, $parentId, $this->identity(), $this->scope());
        $filteredRows = $this->documents->forParent($parentType, $parentId, $this->identity(), $this->scope(), $dataTable->postSearch($this->request));

        return $this->response->setJSON($dataTable->payload(
            $this->request,
            $totalRows,
            $filteredRows,
            fn (array $row): array => $row + ['_actions' => $this->rowActions($row)],
            ['nombre', 'archivo_original']
        ));
    }

    public function addSubpanel(): ResponseInterface
    {
        if (! $this->can('add')) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }
        if (! $this->validate($this->rules())) {
            return $this->response->setStatusCode(422)->setJSON(['exito' => false, 'errors' => $this->validator->getErrors()]);
        }
        try {
            $proposalId = (int) $this->request->getPost('propuesta_id');
            $id = $proposalId > 0
                ? $this->documents->createForProposal($proposalId, $this->request->getPost(), $this->request->getFile('archivo'), $this->identity(), $this->scope(), $this->actorId())
                : $this->documents->create($this->request->getPost(), $this->request->getFile('archivo'), $this->identity(), $this->scope(), $this->actorId());
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['exito' => false, 'message' => $exception->getMessage()]);
        }
        return $this->response->setJSON(['exito' => true, 'id' => $id]);
    }

    public function download(int $id): ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->forbidden();
        }
        $record = $this->documents->find($id, $this->identity(), $this->scope());
        if ($record === null) {
            return $this->response->setStatusCode(404)->setBody('Documento no encontrado.');
        }
        try {
            $path = $this->documents->downloadPath($record);
        } catch (RuntimeException $exception) {
            return $this->response->setStatusCode(404)->setBody($exception->getMessage());
        }
        return $this->response->download($path, null)->setFileName((string) $record['archivo_original']);
    }

    public function delete(int $id): ResponseInterface
    {
        if (! $this->can('delete')) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }
        try {
            $this->documents->softDelete($id, $this->identity(), $this->scope(), $this->actorId());
        } catch (RuntimeException) {
            return $this->response->setStatusCode(404)->setJSON(['exito' => false]);
        }
        return $this->response->setJSON(['exito' => true]);
    }

    /** @param array<string, mixed> $row @return list<array<string, string>> */
    private function rowActions(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $actions = [];
        if ($id > 0 && $this->can('index')) {
            $actions[] = ['name' => 'download', 'label' => 'Descargar', 'url' => site_url('documento/download/' . $id), 'method' => 'GET'];
        }
        if ($id > 0 && $this->can('delete')) {
            $actions[] = ['name' => 'delete', 'label' => 'Desactivar', 'url' => site_url('documento/delete/' . $id), 'method' => 'POST'];
        }
        return $actions;
    }

    /** @return array<string, string> */
    private function rules(): array
    {
        return [
            'nombre' => 'permit_empty|min_length[2]|max_length[245]',
            'cliente_id' => 'permit_empty|max_length[30]',
            'cpotencial_id' => 'permit_empty|is_natural',
            'parent_type' => 'permit_empty|in_list[cliente,cpotencial]',
            'parent_id' => 'permit_empty|is_natural_no_zero',
            'propuesta_id' => 'permit_empty|is_natural_no_zero',
        ];
    }

    private function can(string $operation): bool
    {
        return $this->authorization->allows((int) session('user.perfil_id'), 'documento', $operation);
    }

    private function scope(): string
    {
        return $this->authorization->scope((int) session('user.perfil_id'), 'documento');
    }

    /** @return array<string, mixed> */
    private function identity(): array
    {
        return session('user') ?? [];
    }

    private function actorId(): int
    {
        return (int) session('user.user_id');
    }

    private function forbidden(): ResponseInterface
    {
        return $this->response->setStatusCode(403)->setBody('Operacion no autorizada.');
    }
}

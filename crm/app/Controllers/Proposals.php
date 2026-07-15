<?php

namespace App\Controllers;

use App\Models\CatalogOptionModel;
use App\Services\AuthorizationService;
use App\Services\FollowUpService;
use App\Services\ProposalService;
use App\Support\DataTableResponder;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

final class Proposals extends BaseController
{
    private AuthorizationService $authorization;
    private ProposalService $proposals;

    public function __construct()
    {
        $this->authorization = new AuthorizationService();
        $this->proposals = new ProposalService();
    }

    public function index(): string|ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->forbidden();
        }
        return view('proposals/index', [
            'title' => 'Propuestas | CRM',
            'heading' => 'Propuestas',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Propuestas' => null],
            'permissions' => session('permissions') ?? [],
            'proposals' => $this->proposals->rows($this->identity(), $this->scope(), $this->request->getGet('q')),
            'canAdd' => $this->can('add'),
            'canEdit' => $this->can('edit'),
            'canDelete' => $this->can('delete'),
        ]);
    }

    public function rows(): ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->response->setStatusCode(403)->setJSON(['data' => []]);
        }
        $dataTable = new DataTableResponder();
        $totalRows = $this->proposals->rows($this->identity(), $this->scope());
        $filteredRows = $this->proposals->rows($this->identity(), $this->scope(), $dataTable->search($this->request));
        return $this->response->setJSON($dataTable->payload(
            $this->request,
            $totalRows,
            $filteredRows,
            fn (array $row): array => $row + ['_actions' => $this->rowActions($row)],
            ['nombre', 'canal', 'estado', 'cliente', 'cpotencial', 'contacto', 'ejecutivo']
        ));
    }

    public function add(): string|ResponseInterface
    {
        if (! $this->can('add')) {
            return $this->forbidden();
        }
        if ($this->request->getMethod() === 'POST') {
            if (! $this->validate($this->rules())) {
                return $this->formView($this->request->getPost() ?: [], $this->validator->getErrors(), true);
            }
            try {
                $id = $this->proposals->create($this->request->getPost(), $this->uploadedDocuments(), $this->identity(), $this->scope(), $this->actorId());
            } catch (InvalidArgumentException | RuntimeException $exception) {
                return $this->formView($this->request->getPost() ?: [], ['propuesta' => $exception->getMessage()], true);
            }
            return redirect()->to(site_url('propuesta/' . $id))->with('message', 'Propuesta creada.');
        }
        return $this->formView($this->prefilledDefaults(), [], true);
    }

    public function view(int $id): string|ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->forbidden();
        }
        $proposal = $this->proposals->find($id, $this->identity(), $this->scope());
        if ($proposal === null) {
            return $this->notFound();
        }
        $followUps = new FollowUpService();
        return view('proposals/detail', [
            'title' => 'Propuesta | CRM',
            'heading' => (string) $proposal['nombre'],
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Propuestas' => site_url('propuesta'), (string) $proposal['nombre'] => null],
            'permissions' => session('permissions') ?? [],
            'proposal' => $proposal,
            'documents' => $this->proposals->documents($id, $this->identity(), $this->scope()),
            'followUps' => $followUps->forProposal($id, $this->identity(), $this->authorization->scope((int) session('user.perfil_id'), 'seguimiento')),
            'canEdit' => $this->can('edit'),
            'canDelete' => $this->can('delete'),
            'canAddDocument' => $this->authorization->allows((int) session('user.perfil_id'), 'documento', 'add'),
            'canDeleteDocument' => $this->authorization->allows((int) session('user.perfil_id'), 'documento', 'delete'),
            'canAddFollowUp' => $this->authorization->allows((int) session('user.perfil_id'), 'seguimiento', 'add'),
            'canEditFollowUp' => $this->authorization->allows((int) session('user.perfil_id'), 'seguimiento', 'edit'),
            'canDeleteFollowUp' => $this->authorization->allows((int) session('user.perfil_id'), 'seguimiento', 'delete'),
        ]);
    }

    public function edit(int $id): string|ResponseInterface
    {
        if (! $this->can('edit')) {
            return $this->forbidden();
        }
        $record = $this->proposals->find($id, $this->identity(), $this->scope());
        if ($record === null) {
            return $this->notFound();
        }
        if ($this->request->getMethod() === 'POST') {
            if (! $this->validate($this->rules())) {
                return $this->formView(array_merge($record, $this->request->getPost() ?: []), $this->validator->getErrors(), false);
            }
            try {
                $this->proposals->update($id, $this->request->getPost(), $this->uploadedDocuments(), $this->identity(), $this->scope(), $this->actorId());
            } catch (InvalidArgumentException | RuntimeException $exception) {
                return $this->formView(array_merge($record, $this->request->getPost() ?: []), ['propuesta' => $exception->getMessage()], false);
            }
            return redirect()->to(site_url('propuesta/' . $id))->with('message', 'Propuesta actualizada.');
        }
        return $this->formView($record, [], false);
    }

    public function delete(int $id): ResponseInterface
    {
        if (! $this->can('delete')) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }
        try {
            $this->proposals->softDelete($id, $this->identity(), $this->scope(), $this->actorId());
        } catch (RuntimeException) {
            return $this->response->setStatusCode(404)->setJSON(['exito' => false]);
        }
        return $this->response->setJSON(['exito' => true]);
    }

    public function subpanel(): string|ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->forbidden();
        }
        $parentType = (string) $this->request->getGet('module');
        $parentId = (int) $this->request->getGet('father_id');
        return view('proposals/subpanel', [
            'proposals' => $this->proposals->forParent($parentType, $parentId, $this->identity(), $this->scope()),
            'parentType' => $parentType,
            'parentId' => $parentId,
            'canAddProposal' => $this->can('add'),
            'canEditProposal' => $this->can('edit'),
            'canDeleteProposal' => $this->can('delete'),
        ]);
    }

    private function formView(array $record, array $errors, bool $isNew): string
    {
        $options = new CatalogOptionModel();
        return view('proposals/form', [
            'title' => ($isNew ? 'Nueva propuesta' : 'Editar propuesta') . ' | CRM',
            'heading' => $isNew ? 'Nueva propuesta' : 'Editar propuesta',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Propuestas' => site_url('propuesta'), $isNew ? 'Nueva' : 'Editar' => null],
            'permissions' => session('permissions') ?? [],
            'proposal' => array_merge($record, $this->request->getPost() ?: []),
            'errors' => $errors,
            'isNew' => $isNew,
            'parents' => $this->proposals->parentOptions($this->identity(), $this->scope()),
            'contacts' => $this->safeContacts(array_merge($record, $this->request->getPost() ?: [])),
            'channels' => $options->activeOptions('cgestion'),
            'states' => $options->activeOptions('estado'),
        ]);
    }

    /** @return array<int,string> */
    private function safeContacts(array $record): array
    {
        try {
            return $this->proposals->contactOptionsForInput($record);
        } catch (InvalidArgumentException) {
            return [];
        }
    }

    private function prefilledDefaults(): array
    {
        $type = (string) $this->request->getGet('parent_type');
        $id = (int) $this->request->getGet('parent_id');
        $data = ['ejecutivo_id' => $this->actorId()];
        if ($id > 0 && in_array($type, ['cliente', 'cpotencial'], true)) {
            $data['cliente_id'] = $id . '_' . ($type === 'cliente' ? 1 : 2);
        }
        return $data;
    }

    /** @return list<\CodeIgniter\HTTP\Files\UploadedFile> */
    private function uploadedDocuments(): array
    {
        $files = $this->request->getFileMultiple('documentos');
        return is_array($files) ? $files : [];
    }

    /** @return array<string,string> */
    private function rules(): array
    {
        return [
            'nombre' => 'required|min_length[2]|max_length[245]',
            'canal_id' => 'required|is_natural_no_zero',
            'monto' => 'required|decimal',
            'cliente_id' => 'required|max_length[30]',
            'contacto_id' => 'required|is_natural_no_zero',
            'estado_id' => 'required|is_natural_no_zero',
            'descripcion' => 'permit_empty|max_length[2000]',
        ];
    }

    private function can(string $operation): bool
    {
        return $this->authorization->allows((int) session('user.perfil_id'), 'propuesta', $operation);
    }

    private function scope(): string
    {
        return $this->authorization->scope((int) session('user.perfil_id'), 'propuesta');
    }

    /** @return array<string,mixed> */
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

    private function notFound(): ResponseInterface
    {
        return $this->response->setStatusCode(404)->setBody('Propuesta no encontrada.');
    }

    /** @param array<string,mixed> $row @return list<array<string,string>> */
    private function rowActions(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $actions = [];
        if ($id > 0) {
            $actions[] = ['name' => 'view', 'label' => 'Ver', 'url' => site_url('propuesta/' . $id), 'method' => 'GET'];
        }
        if ($id > 0 && $this->can('edit')) {
            $actions[] = ['name' => 'edit', 'label' => 'Editar', 'url' => site_url('propuesta/' . $id . '/edit'), 'method' => 'GET'];
        }
        if ($id > 0 && $this->can('delete')) {
            $actions[] = ['name' => 'delete', 'label' => 'Desactivar', 'url' => site_url('propuesta/delete/' . $id), 'method' => 'POST'];
        }
        return $actions;
    }
}

<?php

namespace App\Controllers;

use App\Models\CatalogOptionModel;
use App\Models\UserModel;
use App\Services\AuthorizationService;
use App\Services\FollowUpService;
use App\Services\ProposalService;
use App\Support\DataTableResponder;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

final class FollowUps extends BaseController
{
    private AuthorizationService $authorization;
    private FollowUpService $followUps;

    public function __construct()
    {
        $this->authorization = new AuthorizationService();
        $this->followUps = new FollowUpService();
    }

    public function index(): string|ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->forbidden();
        }
        return view('followups/index', [
            'title' => 'Seguimientos | CRM',
            'heading' => 'Seguimientos',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Seguimientos' => null],
            'permissions' => session('permissions') ?? [],
            'followUps' => $this->followUps->rows($this->identity(), $this->scope(), $this->request->getGet('q')),
            'canAdd' => $this->can('add'),
            'canEdit' => $this->can('edit'),
            'canDelete' => $this->can('delete')
        ]);
    }

    public function rows(): ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->response->setStatusCode(403)->setJSON(['data' => []]);
        }
        $dataTable = new DataTableResponder();
        $totalRows = $this->followUps->rows($this->identity(), $this->scope());
        $filteredRows = $this->followUps->rows($this->identity(), $this->scope(), $dataTable->search($this->request));

        return $this->response->setJSON($dataTable->payload(
            $this->request,
            $totalRows,
            $filteredRows,
            fn(array $row): array => $row + ['_actions' => $this->rowActions($row)],
            ['fecha', 'hora', 'actividad', 'estado', 'ejecutivo', 'cliente', 'cpotencial']
        ));
    }

    public function add(): string|ResponseInterface
    {
        if (! $this->can('add')) {
            return $this->forbidden();
        }
        if ($this->request->getMethod() === 'POST') {
            if (! $this->validate($this->rules(true))) {
                return $this->formView($this->request->getPost() ?: [], $this->validator->getErrors(), true);
            }
            try {
                $id = $this->followUps->create($this->request->getPost(), $this->identity(), $this->scope(), $this->actorId(), $this->proposalDocuments());
            } catch (InvalidArgumentException | RuntimeException $e) {
                return $this->formView($this->request->getPost() ?: [], ['followup' => $e->getMessage()], true);
            }
            return redirect()->to(site_url('seguimiento/' . $id))->with('message', 'Seguimiento creado.');
        }
        return $this->formView($this->prefilledDefaults(), [], true);
    }

    public function edit(int $id): string|ResponseInterface
    {
        if (! $this->can('edit')) {
            return $this->forbidden();
        }
        $record = $this->followUps->find($id, $this->identity(), $this->scope());
        if ($record === null) {
            return $this->notFound();
        }
        if ($this->request->getMethod() === 'POST') {
            if (! $this->validate($this->rules(false))) {
                return $this->formView(array_merge($record, $this->request->getPost() ?: []), $this->validator->getErrors(), false);
            }
            try {
                $this->followUps->update($id, $this->request->getPost(), $this->identity(), $this->scope(), $this->actorId());
            } catch (InvalidArgumentException | RuntimeException $e) {
                return $this->formView(array_merge($record, $this->request->getPost() ?: []), ['followup' => $e->getMessage()], false);
            }
            return redirect()->to(site_url('seguimiento/' . $id))->with('message', 'Seguimiento actualizado.');
        }
        return $this->formView($record, [], false);
    }

    public function delete(int $id): ResponseInterface
    {
        if (! $this->can('delete')) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }
        try {
            $this->followUps->softDelete($id, $this->identity(), $this->scope(), $this->actorId());
        } catch (RuntimeException) {
            return $this->response->setStatusCode(404)->setJSON(['exito' => false]);
        }
        return $this->response->setJSON(['exito' => true]);
    }

    public function selectRowForType(): ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->response->setStatusCode(403)->setJSON([]);
        }
        return $this->response->setJSON($this->followUps->parentOptions($this->identity(), $this->scope(), (string) ($this->request->getPost('searchTerm') ?? '')));
    }

    public function subpanelRows(): ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->response->setStatusCode(403)->setJSON(['data' => []]);
        }
        $parentType = (string) $this->request->getPost('module');
        $parentId = (int) $this->request->getPost('father_id');
        $dataTable = new DataTableResponder();
        $totalRows = $this->followUps->forParent($parentType, $parentId, $this->identity(), $this->scope());
        $filteredRows = $this->followUps->forParent($parentType, $parentId, $this->identity(), $this->scope(), $dataTable->postSearch($this->request));

        return $this->response->setJSON($dataTable->payload(
            $this->request,
            $totalRows,
            $filteredRows,
            fn(array $row): array => $row + ['_actions' => $this->rowActions($row)],
            ['fecha', 'hora', 'actividad', 'estado', 'ejecutivo']
        ));
    }

    private function formView(array $record, array $errors, bool $isNew): string
    {
        $options = new CatalogOptionModel();
        $proposals = new ProposalService();
        return view('followups/form', [
            'title' => ($isNew ? 'Nuevo seguimiento' : 'Editar seguimiento') . ' | CRM', 
            'heading' => $isNew ? 'Nuevo seguimiento' : 'Editar seguimiento', 
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Seguimientos' => site_url('seguimiento'), $isNew ? 'Nuevo' : 'Editar' => null], 
            'permissions' => session('permissions') ?? [], 
            'followUp' => array_merge($record, $this->request->getPost() ?: []), 
            'errors' => $errors, 
            'isNew' => $isNew, 
            'activities' => $options->activeOptions('actividad'), 
            'states' => $options->activeOptions('estado'), 
            'executives' => $this->executiveOptions(), 
            'parents' => $this->followUps->parentOptions($this->identity(), $this->scope()),
            'proposals' => $proposals->rows($this->identity(), $this->authorization->scope((int) session('user.perfil_id'), 'propuesta')),
            'channels' => $options->activeOptions('cgestion')
        ]);
    }

    private function prefilledDefaults(): array
    {
        $now = date('Y-m-d H:i');
        [$date, $time] = explode(' ', $now);
        $data = ['fecha' => $date, 'hora' => $time, 'ejecutivo_id' => $this->actorId()];
        $type = (string) $this->request->getGet('parent_type');
        $id = (int) $this->request->getGet('parent_id');
        if ($id > 0 && in_array($type, ['cliente', 'cpotencial'], true)) {
            $data['cliente_id'] = $id . '_' . ($type === 'cliente' ? 1 : 2);
        }
        $proposalId = (int) $this->request->getGet('propuesta_id');
        if ($proposalId > 0) {
            $proposal = (new ProposalService())->find($proposalId, $this->identity(), $this->authorization->scope((int) session('user.perfil_id'), 'propuesta'));
            if ($proposal !== null) {
                $data['propuesta_id'] = $proposalId;
                $data['cliente_id'] = (int) ($proposal['cliente_id'] ?? 0) > 0 ? ((int) $proposal['cliente_id'] . '_1') : ((int) $proposal['cpotencial_id'] . '_2');
            }
        }
        return $data;
    }

    private function executiveOptions(): array
    {
        $rows = (new UserModel())->administrationRows($this->identity(), 'all');
        $options = [];
        foreach ($rows as $row) {
            $options[(int)$row['id']] = (string)$row['nombre'];
        }
        return $options;
    }

    private function rowActions(array $row): array
    {
        $id = (int)($row['id'] ?? 0);
        $actions = [];
        if ($id > 0 && $this->can('edit')) {
            $actions[] = ['name' => 'edit', 'label' => 'Editar', 'url' => site_url('seguimiento/' . $id), 'method' => 'GET'];
        }
        if ($id > 0 && $this->can('delete')) {
            $actions[] = ['name' => 'delete', 'label' => 'Desactivar', 'url' => site_url('seguimiento/delete/' . $id), 'method' => 'POST'];
        }
        return $actions;
    }

    private function rules(bool $requireParent): array
    {
        $rules = ['fecha' => 'required|valid_date[Y-m-d]', 'hora' => 'required|regex_match[/^\d{2}:\d{2}(:\d{2})?$/]', 'actividad_id' => 'required|is_natural_no_zero', 'estado_id' => 'required|is_natural_no_zero', 'ejecutivo_id' => 'required|is_natural_no_zero', 'propuesta_id' => 'permit_empty|is_natural_no_zero', 'monto' => 'permit_empty|decimal', 'descripcion' => 'permit_empty|max_length[2000]', 'adjunto' => 'permit_empty|max_length[250]'];
        if ((int) $this->request->getPost('actividad_id') === 3 && (int) $this->request->getPost('propuesta_id') <= 0) {
            $rules += ['propuesta_nombre' => 'required|min_length[2]|max_length[245]', 'propuesta_canal_id' => 'required|is_natural_no_zero', 'propuesta_monto' => 'required|decimal', 'propuesta_contacto_id' => 'required|is_natural_no_zero', 'propuesta_estado_id' => 'required|is_natural_no_zero'];
        }
        if ($requireParent) {
            $rules['cliente_id'] = 'required|max_length[30]';
        }
        return $rules;
    }

    private function can(string $operation): bool
    {
        return $this->authorization->allows((int)session('user.perfil_id'), 'seguimiento', $operation);
    }
    private function scope(): string
    {
        return $this->authorization->scope((int)session('user.perfil_id'), 'seguimiento');
    }
    private function identity(): array
    {
        return session('user') ?? [];
    }
    private function actorId(): int
    {
        return (int) session('user.user_id');
    }
    /** @return list<\CodeIgniter\HTTP\Files\UploadedFile> */
    private function proposalDocuments(): array
    {
        $files = $this->request->getFileMultiple('propuesta_documentos');
        return is_array($files) ? $files : [];
    }
    private function forbidden(): ResponseInterface
    {
        return $this->response->setStatusCode(403)->setBody('Operacion no autorizada.');
    }
    private function notFound(): ResponseInterface
    {
        return $this->response->setStatusCode(404)->setBody('Seguimiento no encontrado.');
    }
}

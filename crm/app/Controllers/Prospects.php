<?php

namespace App\Controllers;

use App\Support\DataTableResponder;
use App\Models\CatalogOptionModel;
use App\Models\UserModel;
use App\Services\AuthorizationService;
use App\Services\ContactService;
use App\Services\DocumentService;
use App\Services\FollowUpService;
use App\Services\ProspectService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

final class Prospects extends BaseController
{
    private AuthorizationService $authorization;
    private ProspectService $prospects;

    public function __construct()
    {
        $this->authorization = new AuthorizationService();
        $this->prospects = new ProspectService();
    }

    public function index(): string|ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->forbidden();
        }

        $rows = $this->prospects->rows($this->identity(), $this->scope(), $this->request->getGet('q'));

        return view('prospects/index', [
            'title' => 'Clientes Potenciales | CRM',
            'heading' => 'Clientes Potenciales',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Clientes Potenciales' => null],
            'permissions' => session('permissions') ?? [],
            'prospects' => $rows,
            'canAdd' => $this->can('add'),
            'canEdit' => $this->can('edit'),
            'canDelete' => $this->can('delete'),
            'canConvert' => $this->can('convert'),
        ]);
    }

    public function rows(): ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->response->setStatusCode(403)->setJSON(['data' => []]);
        }

        $dataTable = new DataTableResponder();
        $totalRows = $this->prospects->rows($this->identity(), $this->scope());
        $filteredRows = $this->prospects->rows($this->identity(), $this->scope(), $dataTable->search($this->request));

        return $this->response->setJSON($dataTable->payload(
            $this->request,
            $totalRows,
            $filteredRows,
            fn (array $row): array => $row + ['_actions' => $this->rowActions($row)],
            ['razon_social', 'marca', 'rfc', 'sector', 'ejecutivo']
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
                $id = $this->prospects->create($this->request->getPost(), $this->actorId());
            } catch (RuntimeException $exception) {
                return $this->formView($this->request->getPost() ?: [], ['duplicate' => $exception->getMessage()], true);
            }

            return redirect()->to(site_url('cpotencial/' . $id))->with('message', 'Prospecto creado.');
        }

        return $this->formView([], [], true);
    }

    public function edit(int $id): string|ResponseInterface
    {
        if (! $this->can('edit')) {
            return $this->forbidden();
        }

        $record = $this->prospects->find($id, $this->identity(), $this->scope());
        if ($record === null) {
            return $this->notFound();
        }

        if ($this->request->getMethod() === 'POST') {
            if (! $this->validate($this->rules())) {
                return $this->formView(array_merge($record, $this->request->getPost() ?: []), $this->validator->getErrors(), false);
            }

            try {
                $this->prospects->update($id, $this->request->getPost(), $this->actorId());
            } catch (RuntimeException $exception) {
                return $this->formView(array_merge($record, $this->request->getPost() ?: []), ['duplicate' => $exception->getMessage()], false);
            }

            return redirect()->to(site_url('cpotencial/' . $id))->with('message', 'Prospecto actualizado.');
        }

        return $this->formView($record, [], false);
    }

    public function convert(int $id): ResponseInterface
    {
        if (! $this->can('convert')) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }

        try {
            $result = $this->prospects->convertToCustomer($id, $this->identity(), $this->scope(), $this->actorId());
        } catch (RuntimeException $exception) {
            $status = str_contains($exception->getMessage(), 'no encontrado') ? 404 : 422;
            return $this->response->setStatusCode($status)->setJSON([
                'exito' => false,
                'message' => $exception->getMessage(),
            ]);
        }

        return $this->response->setJSON([
            'exito' => true,
            'cliente_id' => $result['cliente_id'],
            'created' => $result['created'],
        ]);
    }

    public function delete(int $id): ResponseInterface
    {
        if (! $this->can('delete')) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }

        if ($this->prospects->find($id, $this->identity(), $this->scope()) === null) {
            return $this->response->setStatusCode(404)->setJSON(['exito' => false]);
        }

        $this->prospects->softDelete($id, $this->actorId());

        return $this->response->setJSON(['exito' => true]);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, string> $errors
     */
    private function formView(array $record, array $errors, bool $isNew): string
    {
        $options = new CatalogOptionModel();

        $contacts = new ContactService();
        $documents = new DocumentService();
        $followUps = new FollowUpService();

        return view('prospects/form', [
            'title' => ($isNew ? 'Nuevo Cliente Potencial' : 'Editar Cliente Potencial') . ' | CRM',
            'heading' => $isNew ? 'Nuevo Cliente Potencial' : 'Editar Cliente Potencial',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Clientes Potenciales' => site_url('cpotencial'), $isNew ? 'Nuevo' : 'Editar' => null],
            'permissions' => session('permissions') ?? [],
            'prospect' => array_merge($record, $this->request->getPost() ?: []),
            'errors' => $errors,
            'isNew' => $isNew,
            'sectors' => $options->activeOptions('sector'),
            'executives' => $this->executiveOptions(),
            'contacts' => ! $isNew && isset($record['id']) ? $contacts->forParent('cpotencial', (int) $record['id'], $this->identity(), $this->authorization->scope((int) session('user.perfil_id'), 'contacto')) : [],
            'canAddContact' => $this->authorization->allows((int) session('user.perfil_id'), 'contacto', 'add'),
            'canEditContact' => $this->authorization->allows((int) session('user.perfil_id'), 'contacto', 'edit'),
            'canDeleteContact' => $this->authorization->allows((int) session('user.perfil_id'), 'contacto', 'delete'),
            'documents' => ! $isNew && isset($record['id']) ? $documents->forParent('cpotencial', (int) $record['id'], $this->identity(), $this->authorization->scope((int) session('user.perfil_id'), 'documento')) : [],
            'canAddDocument' => $this->authorization->allows((int) session('user.perfil_id'), 'documento', 'add'),
            'canDeleteDocument' => $this->authorization->allows((int) session('user.perfil_id'), 'documento', 'delete'),
            'followUps' => ! $isNew && isset($record['id']) && $this->followUpTablesExist() ? $followUps->forParent('cpotencial', (int) $record['id'], $this->identity(), $this->authorization->scope((int) session('user.perfil_id'), 'seguimiento')) : [],
            'canAddFollowUp' => $this->authorization->allows((int) session('user.perfil_id'), 'seguimiento', 'add'),
            'canEditFollowUp' => $this->authorization->allows((int) session('user.perfil_id'), 'seguimiento', 'edit'),
            'canDeleteFollowUp' => $this->authorization->allows((int) session('user.perfil_id'), 'seguimiento', 'delete'),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function executiveOptions(): array
    {
        $rows = (new UserModel())->administrationRows($this->identity(), 'all');
        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row['id']] = (string) $row['nombre'];
        }

        return $options;
    }

    /**
     * @param array<string, mixed> $row
     * @return list<array<string, string>>
     */
    private function rowActions(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $actions = [];
        if ($id > 0 && $this->can('edit')) {
            $actions[] = ['name' => 'edit', 'label' => 'Editar', 'url' => site_url('cpotencial/' . $id), 'method' => 'GET'];
        }
        if ($id > 0 && $this->can('convert') && trim((string) ($row['rfc'] ?? '')) !== '') {
            $actions[] = ['name' => 'convert', 'label' => 'Convertir', 'url' => site_url('cpotencial/convert/' . $id), 'method' => 'POST'];
        }
        if ($id > 0 && $this->can('delete')) {
            $actions[] = ['name' => 'delete', 'label' => 'Desactivar', 'url' => site_url('cpotencial/delete/' . $id), 'method' => 'POST'];
        }

        return $actions;
    }

    /**
     * @return array<string, string>
     */
    private function rules(): array
    {
        return [
            'razon_social' => 'required|min_length[2]|max_length[200]',
            'marca' => 'required|min_length[2]|max_length[150]',
            'rfc' => 'permit_empty|max_length[50]',
            'sector_id' => 'required|is_natural_no_zero',
            'ejecutivo_id' => 'required|is_natural_no_zero',
            'estado' => 'permit_empty|max_length[150]',
            'ciudad' => 'permit_empty|max_length[150]',
            'cp' => 'permit_empty|max_length[5]',
            'direccion' => 'permit_empty|max_length[250]',
        ];
    }

    private function followUpTablesExist(): bool
    {
        $db = db_connect();
        return $db->tableExists('seguimiento') && $db->tableExists('actividad') && $db->tableExists('estado');
    }
    private function can(string $operation): bool
    {
        return $this->authorization->allows((int) session('user.perfil_id'), 'cpotencial', $operation);
    }

    private function scope(): string
    {
        return $this->authorization->scope((int) session('user.perfil_id'), 'cpotencial');
    }

    /**
     * @return array<string, mixed>
     */
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
        return $this->response->setStatusCode(404)->setBody('Prospecto no encontrado.');
    }
}


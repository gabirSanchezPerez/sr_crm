<?php

namespace App\Controllers;

use App\Support\DataTableResponder;
use App\Models\CatalogOptionModel;
use App\Models\UserModel;
use App\Services\AuthorizationService;
use App\Services\ContactService;
use App\Services\CustomerService;
use App\Services\DocumentService;
use App\Services\FollowUpService;
use CodeIgniter\HTTP\ResponseInterface;
use RuntimeException;

final class Customers extends BaseController
{
    private AuthorizationService $authorization;
    private CustomerService $customers;

    public function __construct()
    {
        $this->authorization = new AuthorizationService();
        $this->customers = new CustomerService();
    }

    public function index(): string|ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->forbidden();
        }

        $rows = $this->customers->rows($this->identity(), $this->scope(), $this->request->getGet('q'));

        return view('customers/index', [
            'title' => 'Clientes | CRM',
            'heading' => 'Clientes',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Clientes' => null],
            'permissions' => session('permissions') ?? [],
            'customers' => $rows,
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
        $totalRows = $this->customers->rows($this->identity(), $this->scope());
        $filteredRows = $this->customers->rows($this->identity(), $this->scope(), $dataTable->search($this->request));

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
                $id = $this->customers->create($this->request->getPost(), $this->actorId());
            } catch (RuntimeException $exception) {
                return $this->formView($this->request->getPost() ?: [], ['customer' => $exception->getMessage()], true);
            }

            return redirect()->to(site_url('cliente/' . $id))->with('message', 'Cliente creado.');
        }

        return $this->formView([], [], true);
    }

    public function edit(int $id): string|ResponseInterface
    {
        if (! $this->can('edit')) {
            return $this->forbidden();
        }

        $record = $this->customers->find($id, $this->identity(), $this->scope());
        if ($record === null) {
            return $this->notFound();
        }

        if ($this->request->getMethod() === 'POST') {
            if (! $this->validate($this->rules())) {
                return $this->formView(array_merge($record, $this->request->getPost() ?: []), $this->validator->getErrors(), false);
            }

            try {
                $this->customers->update($id, $this->request->getPost(), $this->actorId());
            } catch (RuntimeException $exception) {
                return $this->formView(array_merge($record, $this->request->getPost() ?: []), ['customer' => $exception->getMessage()], false);
            }

            return redirect()->to(site_url('cliente/' . $id))->with('message', 'Cliente actualizado.');
        }

        return $this->formView($record, [], false);
    }

    public function delete(int $id): ResponseInterface
    {
        if (! $this->can('delete')) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }

        if ($this->customers->find($id, $this->identity(), $this->scope()) === null) {
            return $this->response->setStatusCode(404)->setJSON(['exito' => false]);
        }

        $this->customers->softDelete($id, $this->actorId());

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

        return view('customers/form', [
            'title' => ($isNew ? 'Nuevo cliente' : 'Editar cliente') . ' | CRM',
            'heading' => $isNew ? 'Nuevo cliente' : 'Editar cliente',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Clientes' => site_url('cliente'), $isNew ? 'Nuevo' : 'Editar' => null],
            'permissions' => session('permissions') ?? [],
            'customer' => array_merge($record, $this->request->getPost() ?: []),
            'errors' => $errors,
            'isNew' => $isNew,
            'sectors' => $options->activeOptions('sector'),
            'executives' => $this->executiveOptions(),
            'contacts' => ! $isNew && isset($record['id']) ? $contacts->forParent('cliente', (int) $record['id'], $this->identity(), $this->authorization->scope((int) session('user.perfil_id'), 'contacto')) : [],
            'canAddContact' => $this->authorization->allows((int) session('user.perfil_id'), 'contacto', 'add'),
            'canEditContact' => $this->authorization->allows((int) session('user.perfil_id'), 'contacto', 'edit'),
            'canDeleteContact' => $this->authorization->allows((int) session('user.perfil_id'), 'contacto', 'delete'),
            'documents' => ! $isNew && isset($record['id']) ? $documents->forParent('cliente', (int) $record['id'], $this->identity(), $this->authorization->scope((int) session('user.perfil_id'), 'documento')) : [],
            'canAddDocument' => $this->authorization->allows((int) session('user.perfil_id'), 'documento', 'add'),
            'canDeleteDocument' => $this->authorization->allows((int) session('user.perfil_id'), 'documento', 'delete'),
            'followUps' => ! $isNew && isset($record['id']) && $this->followUpTablesExist() ? $followUps->forParent('cliente', (int) $record['id'], $this->identity(), $this->authorization->scope((int) session('user.perfil_id'), 'seguimiento')) : [],
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
            $actions[] = ['name' => 'edit', 'label' => 'Editar', 'url' => site_url('cliente/' . $id), 'method' => 'GET'];
        }
        if ($id > 0 && $this->can('delete')) {
            $actions[] = ['name' => 'delete', 'label' => 'Desactivar', 'url' => site_url('cliente/delete/' . $id), 'method' => 'POST'];
        }

        return $actions;
    }

    /**
     * @return array<string, string>
     */
    private function rules(): array
    {
        return [
            'razon_social' => 'required|min_length[2]|max_length[250]',
            'marca' => 'required|min_length[2]|max_length[150]',
            'rfc' => 'permit_empty|max_length[50]',
            'sector_id' => 'required|is_natural_no_zero',
            'ejecutivo_id' => 'required|is_natural_no_zero',
            'cpotencial_id' => 'permit_empty|is_natural',
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
        return $this->authorization->allows((int) session('user.perfil_id'), 'cliente', $operation);
    }

    private function scope(): string
    {
        return $this->authorization->scope((int) session('user.perfil_id'), 'cliente');
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
        return $this->response->setStatusCode(404)->setBody('Cliente no encontrado.');
    }
}

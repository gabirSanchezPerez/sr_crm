<?php

namespace App\Controllers;

use App\Support\DataTableResponder;
use App\Services\AuthorizationService;
use App\Services\ContactService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

final class Contacts extends BaseController
{
    private AuthorizationService $authorization;
    private ContactService $contacts;

    public function __construct()
    {
        $this->authorization = new AuthorizationService();
        $this->contacts = new ContactService();
    }

    public function index(): string|ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->forbidden();
        }

        return view('contacts/index', [
            'title' => 'Contactos | CRM',
            'heading' => 'Contactos',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Contactos' => null],
            'permissions' => session('permissions') ?? [],
            'contacts' => $this->contacts->rows($this->identity(), $this->scope(), $this->request->getGet('q')),
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
        $totalRows = $this->contacts->rows($this->identity(), $this->scope());
        $filteredRows = $this->contacts->rows($this->identity(), $this->scope(), $dataTable->search($this->request));

        return $this->response->setJSON($dataTable->payload(
            $this->request,
            $totalRows,
            $filteredRows,
            fn (array $row): array => $row + ['_actions' => $this->rowActions($row)],
            ['nombre', 'correo', 'telefono', 'cliente', 'cpotencial']
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
                $id = $this->contacts->create($this->request->getPost(), $this->identity(), $this->scope(), $this->actorId());
            } catch (InvalidArgumentException|RuntimeException $exception) {
                return $this->formView($this->request->getPost() ?: [], ['contact' => $exception->getMessage()], true);
            }

            return redirect()->to(site_url('contacto/' . $id))->with('message', 'Contacto creado.');
        }

        return $this->formView([], [], true);
    }

    public function edit(int $id): string|ResponseInterface
    {
        if (! $this->can('edit')) {
            return $this->forbidden();
        }

        $record = $this->contacts->find($id, $this->identity(), $this->scope());
        if ($record === null) {
            return $this->notFound();
        }

        if ($this->request->getMethod() === 'POST') {
            if (! $this->validate($this->rules())) {
                return $this->formView(array_merge($record, $this->request->getPost() ?: []), $this->validator->getErrors(), false);
            }

            try {
                $this->contacts->update($id, $this->request->getPost(), $this->identity(), $this->scope(), $this->actorId());
            } catch (InvalidArgumentException|RuntimeException $exception) {
                return $this->formView(array_merge($record, $this->request->getPost() ?: []), ['contact' => $exception->getMessage()], false);
            }

            return redirect()->to(site_url('contacto/' . $id))->with('message', 'Contacto actualizado.');
        }

        return $this->formView($record, [], false);
    }

    public function delete(int $id): ResponseInterface
    {
        if (! $this->can('delete')) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }

        try {
            $this->contacts->softDelete($id, $this->identity(), $this->scope(), $this->actorId());
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

        return view('contacts/subpanel', [
            'contacts' => [],
            'parentType' => (string) $this->request->getGet('module'),
            'parentId' => (int) $this->request->getGet('father_id'),
            'canAddContact' => $this->can('add'),
            'canEditContact' => $this->can('edit'),
            'canDeleteContact' => $this->can('delete'),
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
        $totalRows = $this->contacts->forParent($parentType, $parentId, $this->identity(), $this->scope());
        $filteredRows = $this->contacts->forParent($parentType, $parentId, $this->identity(), $this->scope(), $dataTable->postSearch($this->request));

        return $this->response->setJSON($dataTable->payload(
            $this->request,
            $totalRows,
            $filteredRows,
            fn (array $row): array => $row + ['_actions' => $this->rowActions($row)],
            ['nombre', 'correo', 'telefono']
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
            $id = $this->contacts->create($this->request->getPost(), $this->identity(), $this->scope(), $this->actorId());
        } catch (InvalidArgumentException|RuntimeException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['exito' => false, 'message' => $exception->getMessage()]);
        }

        return $this->response->setJSON(['exito' => true, 'id' => $id]);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, string> $errors
     */
    private function formView(array $record, array $errors, bool $isNew): string
    {
        return view('contacts/form', [
            'title' => ($isNew ? 'Nuevo contacto' : 'Editar contacto') . ' | CRM',
            'heading' => $isNew ? 'Nuevo contacto' : 'Editar contacto',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Contactos' => site_url('contacto'), $isNew ? 'Nuevo' : 'Editar' => null],
            'permissions' => session('permissions') ?? [],
            'contact' => array_merge($record, $this->prefilledParent(), $this->request->getPost() ?: []),
            'errors' => $errors,
            'isNew' => $isNew,
            'parents' => $this->parentOptions(),
        ]);
    }

    /** @return array<string, mixed> */
    private function prefilledParent(): array
    {
        $parentType = (string) $this->request->getGet('parent_type');
        $parentId = (int) $this->request->getGet('parent_id');
        if ($parentType === 'cliente' && $parentId > 0) {
            return ['cliente_id' => $parentId . '_1'];
        }
        if ($parentType === 'cpotencial' && $parentId > 0) {
            return ['cliente_id' => $parentId . '_2'];
        }

        return [];
    }

    /** @return array<string, string> */
    private function parentOptions(): array
    {
        $options = [];
        foreach (db_connect()->table('cliente')->select('id, razon_social, marca')->where('deleted', 0)->orderBy('razon_social')->get()->getResultArray() as $row) {
            if (! $this->contacts->parentIsAccessible('cliente', (int) $row['id'], $this->identity(), $this->scope())) {
                continue;
            }
            $key = (int) $row['id'] . '_1';
            $options[$key] = 'Cliente: ' . $row['razon_social'] . ' (' . $row['marca'] . ')';
        }
        foreach (db_connect()->table('cpotencial')->select('id, razon_social, marca')->where('deleted', 0)->where('cliente_id', null)->orderBy('razon_social')->get()->getResultArray() as $row) {
            if (! $this->contacts->parentIsAccessible('cpotencial', (int) $row['id'], $this->identity(), $this->scope())) {
                continue;
            }
            $key = (int) $row['id'] . '_2';
            $options[$key] = 'Prospecto: ' . $row['razon_social'] . ' (' . $row['marca'] . ')';
        }

        return $options;
    }

    /** @param array<string, mixed> $row @return list<array<string, string>> */
    private function rowActions(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        $actions = [];
        if ($id > 0 && $this->can('edit')) {
            $actions[] = ['name' => 'edit', 'label' => 'Editar', 'url' => site_url('contacto/' . $id), 'method' => 'GET'];
        }
        if ($id > 0 && $this->can('delete')) {
            $actions[] = ['name' => 'delete', 'label' => 'Desactivar', 'url' => site_url('contacto/delete/' . $id), 'method' => 'POST'];
        }

        return $actions;
    }

    /** @return array<string, string> */
    private function rules(): array
    {
        return [
            'nombre' => 'required|min_length[2]|max_length[245]',
            'telefono' => 'required|min_length[7]|max_length[10]',
            'celular' => 'permit_empty|min_length[7]|max_length[10]',
            'otro_num' => 'permit_empty|min_length[7]|max_length[10]',
            'puesto' => 'permit_empty|max_length[145]',
            'departamento' => 'permit_empty|max_length[145]',
            'correo' => 'required|valid_email|max_length[245]',
            'descripcion' => 'permit_empty|max_length[1000]',
            'cliente_id' => 'permit_empty|max_length[30]',
            'cpotencial_id' => 'permit_empty|is_natural',
            'parent_type' => 'permit_empty|in_list[cliente,cpotencial]',
            'parent_id' => 'permit_empty|is_natural_no_zero',
            'estado' => 'permit_empty|max_length[150]',
            'ciudad' => 'permit_empty|max_length[150]',
            'cp' => 'permit_empty|max_length[5]',
            'direccion' => 'permit_empty|max_length[250]',
        ];
    }

    private function can(string $operation): bool
    {
        return $this->authorization->allows((int) session('user.perfil_id'), 'contacto', $operation);
    }

    private function scope(): string
    {
        return $this->authorization->scope((int) session('user.perfil_id'), 'contacto');
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

    private function notFound(): ResponseInterface
    {
        return $this->response->setStatusCode(404)->setBody('Contacto no encontrado.');
    }
}
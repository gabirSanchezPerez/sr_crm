<?php

namespace App\Controllers;

use App\Support\DataTableResponder;
use App\Models\CatalogOptionModel;
use App\Models\ProfileModel;
use App\Models\UserModel;
use App\Services\AuthorizationService;
use App\Services\UserAdministrationService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

final class Users extends BaseController
{
    private AuthorizationService $authorization;
    private UserAdministrationService $service;

    public function __construct()
    {
        $this->authorization = new AuthorizationService();
        $this->service = new UserAdministrationService();
    }

    public function index(): string|ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->forbidden();
        }

        $identity = $this->identity();
        $scope = $this->authorization->scope((int) $identity['perfil_id'], 'usuario');

        return view('users/index', [
            'title' => 'Usuarios | CRM',
            'heading' => 'Usuarios',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Usuarios' => null],
            'permissions' => session('permissions') ?? [],
            'users' => (new UserModel())->administrationRows($identity, $scope, $this->request->getGet('q')),
            'canAdd' => $this->can('add'),
            'canEdit' => $this->can('edit'),
            'canDelete' => $this->can('delete'),
            'isFullAdmin' => $scope === 'all',
        ]);
    }

    public function rows(): ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->response->setStatusCode(403)->setJSON(['data' => []]);
        }

        $identity = $this->identity();
        $scope = $this->authorization->scope((int) $identity['perfil_id'], 'usuario');
        $dataTable = new DataTableResponder();
        $totalRows = (new UserModel())->administrationRows($identity, $scope);
        $filteredRows = (new UserModel())->administrationRows($identity, $scope, $dataTable->search($this->request));

        return $this->response->setJSON($dataTable->payload($this->request, $totalRows, $filteredRows, null, ['nombre', 'usuario', 'correo', 'perfil', 'cgestion']));
    }

    public function add(): string|ResponseInterface
    {
        if (! $this->can('add')) {
            return $this->forbidden();
        }

        if ($this->request->getMethod() === 'POST') {
            $rules = $this->rules(true);
            if (! $this->validate($rules)) {
                return $this->formView(null, $this->validator->getErrors());
            }

            $id = $this->service->createUser($this->payload(), $this->selectedUnits(), (int) $this->identity()['user_id']);
            return redirect()->to(site_url('profile/' . $id))->with('message', 'Usuario creado.');
        }

        return $this->formView(null);
    }

    public function edit(int $id): string|ResponseInterface
    {
        if (! $this->can('edit')) {
            return $this->forbidden();
        }

        $model = new UserModel();
        $user = $model->findActiveById($id);
        if ($user === null || ! $this->userIsVisible($user)) {
            return $this->forbidden();
        }

        if ($this->request->getMethod() === 'POST') {
            $rules = $this->rules(false, $id);
            if (! $this->validate($rules)) {
                return $this->formView($id, $this->validator->getErrors());
            }

            $this->service->updateUser($id, $this->payload(), $this->selectedUnits(), (int) $this->identity()['user_id']);
            return redirect()->to(site_url('profile/' . $id))->with('message', 'Usuario actualizado.');
        }

        return $this->formView($id);
    }

    public function delete(int $id): ResponseInterface
    {
        if (! $this->can('delete')) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }

        $user = (new UserModel())->findActiveById($id);
        if ($user === null || ! $this->userIsVisible($user)) {
            return $this->response->setStatusCode(404)->setJSON(['exito' => false]);
        }

        $this->service->deactivateUser($id, (int) $this->identity()['user_id']);
        return $this->response->setJSON(['exito' => true]);
    }

    public function changeUC(int $id): ResponseInterface
    {
        $identity = $this->identity();
        if (! in_array($id, (new UserModel())->commercialUnitIds((int) $identity['user_id']), true)) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }

        $identity['ucomercial_id'] = $id;
        session()->set('user', $identity);
        return $this->response->setJSON(['exito' => true, 'session' => $identity]);
    }

    public function transferAccount(int $id): string|ResponseInterface
    {
        if (! $this->can('edit')) {
            return $this->forbidden();
        }

        $user = (new UserModel())->findActiveById($id);
        if ($user === null || ! $this->userIsVisible($user)) {
            return $this->forbidden();
        }

        return view('users/transfer', [
            'title' => 'Transferir cuentas | CRM',
            'heading' => 'Transferir cuentas',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Usuarios' => site_url('usuario'), 'Transferir' => null],
            'permissions' => session('permissions') ?? [],
            'user' => $user,
            'users' => (new UserModel())->administrationRows($this->identity(), 'all'),
            'clients' => $this->accounts('cliente', $id),
            'prospects' => $this->accounts('cpotencial', $id),
        ]);
    }

    public function transfering(): ResponseInterface
    {
        if (! $this->can('edit')) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }

        $fromUserId = (int) $this->request->getPost('fromUser');
        $toUserId = (int) $this->request->getPost('newEjecutivo');
        $postedAccounts = (array) $this->request->getPost('accounts');
        $accounts = [
            'A' => array_map('intval', (array) ($postedAccounts['A'] ?? [])),
            'CP' => array_map('intval', (array) ($postedAccounts['CP'] ?? [])),
        ];

        try {
            $result = $this->service->transferAccounts($fromUserId, $toUserId, $accounts, (int) $this->identity()['user_id']);
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['exito' => false, 'message' => $exception->getMessage()]);
        } catch (RuntimeException $exception) {
            log_message('error', 'Account transfer failed: {message}', ['message' => $exception->getMessage()]);

            return $this->response->setStatusCode(500)->setJSON(['exito' => false, 'message' => 'No fue posible completar la transferencia.']);
        }

        return $this->response->setJSON(['exito' => true] + $result);
    }

    private function formView(?int $id, array $errors = []): string
    {
        $model = new UserModel();
        $user = $id === null ? [] : ($model->findActiveById($id) ?? []);
        $catalogs = new CatalogOptionModel();

        return view('users/form', [
            'title' => ($id === null ? 'Nuevo usuario' : 'Perfil') . ' | CRM',
            'heading' => $id === null ? 'Nuevo usuario' : 'Perfil de usuario',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Usuarios' => site_url('usuario'), $id === null ? 'Nuevo' : 'Perfil' => null],
            'permissions' => session('permissions') ?? [],
            'user' => array_merge($user, $this->request->getPost() ?: []),
            'selectedUnits' => $this->request->getPost('ucomercial_id') !== null ? $this->selectedUnits() : ($id === null ? [] : $model->commercialUnitIds($id)),
            'profiles' => (new ProfileModel())->options(),
            'managementChannels' => $catalogs->activeOptions('cgestion'),
            'commercialUnits' => $catalogs->activeOptions('ucomercial'),
            'errors' => $errors,
            'isNew' => $id === null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(): array
    {
        return [
            'nombre' => trim((string) $this->request->getPost('nombre')),
            'usuario' => trim((string) $this->request->getPost('usuario')),
            'correo' => trim((string) $this->request->getPost('correo')),
            'contrasenia' => (string) $this->request->getPost('contrasenia'),
            'perfil_id' => (int) $this->request->getPost('perfil_id'),
            'cgestion_id' => (int) $this->request->getPost('cgestion_id'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function rules(bool $isNew, ?int $id = null): array
    {
        $suffix = $id === null ? '' : ',id,' . $id;
        return [
            'nombre' => 'required|min_length[3]|max_length[150]',
            'usuario' => 'required|min_length[2]|max_length[100]|is_unique[usuario.usuario' . $suffix . ']',
            'correo' => 'required|valid_email|max_length[150]|is_unique[usuario.correo' . $suffix . ']',
            'contrasenia' => ($isNew ? 'required|' : 'permit_empty|') . 'min_length[8]|max_length[150]',
            'perfil_id' => 'required|is_natural_no_zero',
            'cgestion_id' => 'required|is_natural_no_zero',
            'ucomercial_id.*' => 'permit_empty|is_natural_no_zero',
        ];
    }

    /**
     * @return list<int>
     */
    private function selectedUnits(): array
    {
        return array_values(array_unique(array_map('intval', (array) $this->request->getPost('ucomercial_id'))));
    }

    private function can(string $operation): bool
    {
        return $this->authorization->allows((int) $this->identity()['perfil_id'], 'usuario', $operation);
    }

    /**
     * @return array<string, mixed>
     */
    private function identity(): array
    {
        return session('user') ?? [];
    }

    /**
     * @param array<string, mixed> $user
     */
    private function userIsVisible(array $user): bool
    {
        $identity = $this->identity();
        $unitIds = (new UserModel())->commercialUnitIds((int) $user['id']);
        if ($unitIds === []) {
            return $this->authorization->recordIsInScope($identity, 'usuario', (int) $user['id']);
        }

        foreach ($unitIds as $unitId) {
            if ($this->authorization->recordIsInScope($identity, 'usuario', (int) $user['id'], $unitId)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function accounts(string $table, int $userId): array
    {
        return db_connect()->table($table)
            ->select("id, CONCAT(razon_social, ' (', marca, ')') AS nombre", false)
            ->where('ejecutivo_id', $userId)
            ->where('deleted', 0)
            ->orderBy('razon_social', 'ASC')
            ->get()
            ->getResultArray();
    }

    private function forbidden(): ResponseInterface
    {
        return $this->response->setStatusCode(403)->setBody('Operacion no autorizada.');
    }
}

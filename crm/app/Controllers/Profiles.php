<?php

namespace App\Controllers;

use App\Support\DataTableResponder;
use App\Models\ProfileModel;
use App\Services\AuthorizationService;
use CodeIgniter\HTTP\ResponseInterface;

final class Profiles extends BaseController
{
    private AuthorizationService $authorization;

    public function __construct()
    {
        $this->authorization = new AuthorizationService();
    }

    public function index(): string|ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->forbidden();
        }

        return view('profiles/index', [
            'title' => 'Perfiles | CRM',
            'heading' => 'Perfiles',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Perfiles' => null],
            'permissions' => session('permissions') ?? [],
            'profiles' => (new ProfileModel())->where('deleted', 0)->orderBy('nombre')->findAll(),
            'canAdd' => $this->can('add'),
            'canEdit' => $this->can('edit'),
        ]);
    }

    public function add(): string|ResponseInterface
    {
        if (! $this->can('add')) {
            return $this->forbidden();
        }

        if ($this->request->getMethod() === 'POST') {
            if (! $this->validate(['nombre' => 'required|min_length[3]|max_length[150]|is_unique[perfil.nombre]'])) {
                return $this->formView(null, $this->validator->getErrors());
            }

            $id = (new ProfileModel())->insert([
                'nombre' => trim((string) $this->request->getPost('nombre')),
                'deleted' => 0,
                'u_crea' => (int) session('user.user_id'),
                'f_creacion' => date('Y-m-d H:i:s'),
            ], true);

            return redirect()->to(site_url('perfil/' . $id))->with('message', 'Perfil creado.');
        }

        return $this->formView(null);
    }

    public function edit(int $id): string|ResponseInterface
    {
        if (! $this->can('edit')) {
            return $this->forbidden();
        }

        $model = new ProfileModel();
        $profile = $model->where('deleted', 0)->find($id);
        if ($profile === null) {
            return $this->forbidden();
        }

        if ($this->request->getMethod() === 'POST') {
            if (! $this->validate(['nombre' => 'required|min_length[3]|max_length[150]|is_unique[perfil.nombre,id,' . $id . ']'])) {
                return $this->formView($id, $this->validator->getErrors());
            }

            $model->update($id, [
                'nombre' => trim((string) $this->request->getPost('nombre')),
                'u_modifica' => (int) session('user.user_id'),
                'f_modificacion' => date('Y-m-d H:i:s'),
            ]);

            return redirect()->to(site_url('perfil/' . $id))->with('message', 'Perfil actualizado.');
        }

        return $this->formView($id);
    }

    public function rows(): ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->response->setStatusCode(403)->setJSON(['data' => []]);
        }

        $dataTable = new DataTableResponder();
        $totalRows = (new ProfileModel())->where('deleted', 0)->orderBy('nombre')->findAll();
        $search = $dataTable->search($this->request);
        $model = (new ProfileModel())->where('deleted', 0)->orderBy('nombre');
        if ($search !== '') {
            $model->like('nombre', $search);
        }
        $filteredRows = $model->findAll();

        return $this->response->setJSON($dataTable->payload($this->request, $totalRows, $filteredRows, null, ['nombre']));
    }

    private function formView(?int $id, array $errors = []): string
    {
        $profile = $id === null ? [] : ((new ProfileModel())->find($id) ?? []);

        return view('profiles/form', [
            'title' => ($id === null ? 'Nuevo perfil' : 'Perfil') . ' | CRM',
            'heading' => $id === null ? 'Nuevo perfil' : 'Editar perfil',
            'breadcrumbs' => ['Inicio' => site_url('home'), 'Perfiles' => site_url('perfil'), $id === null ? 'Nuevo' : 'Editar' => null],
            'permissions' => session('permissions') ?? [],
            'profile' => array_merge($profile, $this->request->getPost() ?: []),
            'errors' => $errors,
            'isNew' => $id === null,
        ]);
    }

    private function can(string $operation): bool
    {
        return $this->authorization->allows((int) session('user.perfil_id'), 'perfil', $operation);
    }

    private function forbidden(): ResponseInterface
    {
        return $this->response->setStatusCode(403)->setBody('Operacion no autorizada.');
    }
}

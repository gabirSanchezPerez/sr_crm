<?php

namespace App\Controllers;

use App\Models\UserModel;
use App\Services\AuthorizationService;
use CodeIgniter\HTTP\RedirectResponse;

final class Auth extends BaseController
{
    public function login(): string|RedirectResponse
    {
        if (session()->has('user')) {
            return redirect()->to(site_url('home'));
        }

        if ($this->request->getMethod() === 'POST') {
            $rules = [
                'identity' => 'required|min_length[2]|max_length[250]',
                'password' => 'required|min_length[5]|max_length[250]',
            ];

            if (! $this->validate($rules)) {
                return view('auth/login', ['title' => 'Acceso | CRM', 'validation' => $this->validator]);
            }

            $model = new UserModel();
            $user = $model->findActiveByIdentity(trim((string) $this->request->getPost('identity')));
            if ($user === null || ! $model->verifyPassword($user, (string) $this->request->getPost('password'))) {
                return redirect()->back()->withInput()->with('error', 'No fue posible iniciar sesión con las credenciales proporcionadas.');
            }

            session()->regenerate(true);
            $commercialUnitIds = $model->commercialUnitIds((int) $user['id']);
            session()->set('user', [
                'user_id' => (int) $user['id'],
                'nombre' => $user['nombre'],
                'correo' => $user['correo'],
                'perfil_id' => (int) $user['perfil_id'],
                'cgestion_id' => (int) $user['cgestion_id'],
                'ucomercial_id' => $commercialUnitIds[0] ?? 0,
                'ucomercial_ids' => $commercialUnitIds,
            ]);
            session()->set('permissions', (new AuthorizationService())->permissionsForProfile((int) $user['perfil_id']));
            $model->update($user['id'], ['conection_end' => date('Y-m-d H:i:s')]);

            return redirect()->to((string) (session()->get('requested_page') ?: site_url('home')))
                ->with('success', 'Sesión iniciada correctamente.');
        }

        return view('auth/login', ['title' => 'Acceso | CRM']);
    }

    public function logout(): RedirectResponse
    {
        session()->destroy();
        return redirect()->to(site_url('login'))->with('success', 'La sesión se cerró correctamente.');
    }
}

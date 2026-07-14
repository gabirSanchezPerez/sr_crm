<?php

namespace App\Controllers;

use App\Support\DataTableResponder;
use App\Catalogs\CatalogDefinition;
use App\Catalogs\CatalogActionPresenter;
use App\Catalogs\CatalogModel;
use App\Catalogs\CatalogPresenter;
use App\Catalogs\CatalogService;
use App\Services\AuthorizationService;
use CodeIgniter\HTTP\ResponseInterface;

abstract class CatalogController extends BaseController
{
    private ?CatalogService $catalogService = null;
    private ?CatalogPresenter $catalogPresenter = null;
    private ?CatalogActionPresenter $catalogActionPresenter = null;
    private AuthorizationService $authorization;

    public function __construct()
    {
        $this->authorization = new AuthorizationService();
    }

    abstract protected function definition(): CatalogDefinition;

    public function index(): string|ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->forbidden();
        }

        $rows = $this->service()->rows($this->request->getGet('q'));
        $actions = $this->actions();

        return view('catalogs/index', $this->presenter()->indexData(
            $rows,
            $actions->pageActions(),
            $actions->rowActionsById($rows),
        ));
    }

    public function rows(): ResponseInterface
    {
        if (! $this->can('index')) {
            return $this->response->setStatusCode(403)->setJSON(['data' => []]);
        }

        $dataTable = new DataTableResponder();
        $totalRows = $this->service()->rows();
        $filteredRows = $this->service()->rows($dataTable->search($this->request));

        return $this->response->setJSON($dataTable->payload(
            $this->request,
            $totalRows,
            $filteredRows,
            fn (array $row): array => $this->actions()->rowsWithActions([$row])[0],
            array_keys($this->definition()->listFields())
        ));
    }

    public function add(): string|ResponseInterface
    {
        if (! $this->can('add')) {
            return $this->forbidden();
        }

        if ($this->request->getMethod() === 'POST') {
            if (! $this->validate($this->definition()->validationRules())) {
                return $this->formView([], $this->validator->getErrors(), true);
            }

            $id = $this->service()->create($this->request->getPost(), $this->actorId());
            return redirect()->to(site_url($this->definition()->route . '/' . $id))
                ->with('message', $this->definition()->singularLabel . ' creado.');
        }

        return $this->formView([], [], true);
    }

    public function edit(int $id): string|ResponseInterface
    {
        if (! $this->can('edit')) {
            return $this->forbidden();
        }

        $record = $this->service()->find($id);
        if ($record === null) {
            return $this->notFound();
        }

        if ($this->request->getMethod() === 'POST') {
            if (! $this->validate($this->definition()->validationRules($id))) {
                return $this->formView(array_merge($record, $this->request->getPost() ?: []), $this->validator->getErrors(), false);
            }

            $this->service()->update($id, $this->request->getPost(), $this->actorId());
            return redirect()->to(site_url($this->definition()->route . '/' . $id))
                ->with('message', $this->definition()->singularLabel . ' actualizado.');
        }

        return $this->formView($record, [], false);
    }

    public function delete(int $id): ResponseInterface
    {
        if (! $this->definition()->supportsDelete || ! $this->can('delete')) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false]);
        }

        if ($this->service()->find($id) === null) {
            return $this->response->setStatusCode(404)->setJSON(['exito' => false]);
        }

        $this->service()->softDelete($id, $this->actorId());

        return $this->response->setJSON(['exito' => true]);
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, string> $errors
     */
    private function formView(array $record, array $errors, bool $isNew): string
    {
        return view('catalogs/form', $this->presenter()->formData(
            array_merge($record, $this->request->getPost() ?: []),
            $errors,
            $isNew,
        ));
    }

    private function service(): CatalogService
    {
        if ($this->catalogService === null) {
            $this->catalogService = new CatalogService(new CatalogModel($this->definition()));
        }

        return $this->catalogService;
    }

    private function presenter(): CatalogPresenter
    {
        if ($this->catalogPresenter === null) {
            $this->catalogPresenter = new CatalogPresenter($this->definition());
        }

        return $this->catalogPresenter;
    }

    private function actions(): CatalogActionPresenter
    {
        if ($this->catalogActionPresenter === null) {
            $this->catalogActionPresenter = new CatalogActionPresenter(
                $this->definition(),
                $this->authorization,
                (int) session('user.perfil_id'),
            );
        }

        return $this->catalogActionPresenter;
    }

    private function can(string $operation): bool
    {
        return $this->authorization->allows((int) session('user.perfil_id'), $this->definition()->module, $operation);
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
        return $this->response->setStatusCode(404)->setBody('Registro no encontrado.');
    }
}

<?php

namespace App\Controllers;

use App\Services\AuthorizationService;
use App\Services\SpreadsheetImportService;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use RuntimeException;

final class Imports extends BaseController
{
    private AuthorizationService $authorization;
    private SpreadsheetImportService $imports;

    public function __construct()
    {
        $this->authorization = new AuthorizationService();
        $this->imports = new SpreadsheetImportService();
    }

    public function customers(): ResponseInterface
    {
        return $this->handle('cliente', 'add', static fn (SpreadsheetImportService $service, $file, array $identity, int $actorId): array => $service->importCustomers($file, $identity, $actorId));
    }

    public function prospects(): ResponseInterface
    {
        return $this->handle('cpotencial', 'add', static fn (SpreadsheetImportService $service, $file, array $identity, int $actorId): array => $service->importProspects($file, $identity, $actorId));
    }

    public function users(): ResponseInterface
    {
        return $this->handle('usuario', 'add', static fn (SpreadsheetImportService $service, $file, array $identity, int $actorId): array => $service->importUsers($file, $identity, $actorId));
    }

    private function handle(string $module, string $operation, callable $importer): ResponseInterface
    {
        if (! $this->authorization->allows((int) session('user.perfil_id'), $module, $operation)) {
            return $this->response->setStatusCode(403)->setJSON(['exito' => false, 'message' => 'Operacion no autorizada.']);
        }

        $file = $this->request->getFile('archivo');
        if ($file === null) {
            return $this->response->setStatusCode(422)->setJSON(['exito' => false, 'message' => 'Selecciona un archivo para importar.']);
        }

        try {
            $summary = $importer($this->imports, $file, $this->identity(), $this->actorId());
        } catch (InvalidArgumentException $exception) {
            return $this->response->setStatusCode(422)->setJSON(['exito' => false, 'message' => $exception->getMessage()]);
        } catch (RuntimeException $exception) {
            log_message('error', 'Spreadsheet import failed: {message}', ['message' => $exception->getMessage()]);

            return $this->response->setStatusCode(500)->setJSON(['exito' => false, 'message' => 'No fue posible completar la importacion.']);
        }

        return $this->response->setJSON($summary);
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
}
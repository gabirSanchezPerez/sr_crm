<?php

namespace App\Services;

use App\Models\DocumentModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

final class DocumentService
{
    private const MAX_BYTES = 10485760;
    private const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'jpg', 'jpeg', 'png'];

    public function __construct(
        private readonly DocumentModel $model = new DocumentModel(),
        private readonly string $storageRoot = WRITEPATH . 'uploads/documents'
    ) {
    }

    /** @return list<array<string, mixed>> */
    public function rows(array $identity, string $scope, ?string $search = null): array
    {
        return $this->model->activeRows($identity, $scope, $search);
    }

    /** @return list<array<string, mixed>> */
    public function forParent(string $parentType, int $parentId, array $identity, string $scope, ?string $search = null): array
    {
        if (! $this->model->parentIsAccessible($parentType, $parentId, $identity, $scope)) {
            return [];
        }
        return $this->model->activeByParent($parentType, $parentId, $identity, $scope, $search);
    }

    public function parentIsAccessible(string $parentType, int $parentId, array $identity, string $scope): bool
    {
        return $this->model->parentIsAccessible($parentType, $parentId, $identity, $scope);
    }

    public function find(int $id, array $identity, string $scope): ?array
    {
        return $this->model->activeById($id, $identity, $scope);
    }

    /** @return list<array<string, mixed>> */
    public function forProposal(int $proposalId, array $identity, string $scope, ?string $search = null): array
    {
        if (! $this->model->proposalIsAccessible($proposalId, $identity, $scope)) {
            return [];
        }
        return $this->model->activeByProposal($proposalId, $identity, $scope, $search);
    }

    /** @param array<string, mixed> $input */
    public function create(array $input, ?UploadedFile $file, array $identity, string $scope, int $actorId): int
    {
        [$parentType, $parentId] = $this->parentFromInput($input);
        if (! $this->model->parentIsAccessible($parentType, $parentId, $identity, $scope)) {
            throw new InvalidArgumentException('La cuenta padre no esta disponible para este usuario.');
        }
        if ($file === null || (! $file->isValid() && ! $this->isTestingUpload($file))) {
            throw new InvalidArgumentException('Selecciona un archivo valido.');
        }
        $this->validateUpload($file);
        $stored = $this->storeFile($file);
        $data = [
            'cliente_id' => $parentType === 'cliente' ? $parentId : null,
            'cpotencial_id' => $parentType === 'cpotencial' ? $parentId : null,
            'nombre' => trim((string) ($input['nombre'] ?? '')) ?: pathinfo($file->getClientName(), PATHINFO_FILENAME),
            'archivo_original' => $this->safeClientName($file->getClientName()),
            'archivo_ruta' => $stored,
            'mime' => $file->getClientMimeType(),
            'tamano' => $file->getSize(),
            'u_crea' => $actorId,
            'f_creacion' => date('Y-m-d H:i:s'),
            'deleted' => 0,
        ];
        $id = $this->model->insert($data, true);
        if ($id === false) {
            @unlink($this->absolutePath($stored));
            throw new RuntimeException('No fue posible crear el documento.');
        }
        return (int) $id;
    }

    /** @param array<string,mixed> $input */
    public function createForProposal(int $proposalId, array $input, ?UploadedFile $file, array $identity, string $scope, int $actorId): int
    {
        if (! $this->model->proposalIsAccessible($proposalId, $identity, $scope)) {
            throw new InvalidArgumentException('La propuesta no esta disponible para este usuario.');
        }
        if ($file === null || (! $file->isValid() && ! $this->isTestingUpload($file))) {
            throw new InvalidArgumentException('Selecciona un archivo valido.');
        }
        $this->validateUpload($file);
        $stored = $this->storeFile($file);
        $data = [
            'cliente_id' => null,
            'cpotencial_id' => null,
            'propuesta_id' => $proposalId,
            'nombre' => trim((string) ($input['nombre'] ?? '')) ?: pathinfo($file->getClientName(), PATHINFO_FILENAME),
            'archivo_original' => $this->safeClientName($file->getClientName()),
            'archivo_ruta' => $stored,
            'mime' => $file->getClientMimeType(),
            'tamano' => $file->getSize(),
            'u_crea' => $actorId,
            'f_creacion' => date('Y-m-d H:i:s'),
            'deleted' => 0,
        ];
        $id = $this->model->insert($data, true);
        if ($id === false) {
            @unlink($this->absolutePath($stored));
            throw new RuntimeException('No fue posible crear el documento.');
        }
        return (int) $id;
    }

    public function softDelete(int $id, array $identity, string $scope, int $actorId): void
    {
        if ($this->find($id, $identity, $scope) === null) {
            throw new RuntimeException('Documento no encontrado.');
        }
        if (! $this->model->update($id, ['deleted' => 1, 'u_modifica' => $actorId, 'f_modificacion' => date('Y-m-d H:i:s')])) {
            throw new RuntimeException('No fue posible desactivar el documento.');
        }
    }

    /** @param array<string, mixed> $record */
    public function downloadPath(array $record): string
    {
        $path = $this->absolutePath((string) ($record['archivo_ruta'] ?? ''));
        if (! is_file($path)) {
            throw new RuntimeException('Archivo no encontrado.');
        }
        return $path;
    }

    public function absolutePath(string $relativePath): string
    {
        $clean = str_replace(['\\', '../', '..\\'], ['/', '', ''], $relativePath);
        if (! str_starts_with($clean, 'documents/')) {
            throw new RuntimeException('Ruta de archivo invalida.');
        }
        return rtrim(WRITEPATH . 'uploads', DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean);
    }

    public function removeStoredFile(string $relativePath): void
    {
        try {
            $path = $this->absolutePath($relativePath);
        } catch (RuntimeException) {
            return;
        }

        if (is_file($path)) {
            @unlink($path);
        }
    }

    private function isTestingUpload(UploadedFile $file): bool
    {
        return ENVIRONMENT === 'testing' && $file->getError() === UPLOAD_ERR_OK && is_file($file->getTempName());
    }

    /** @return array{0:string,1:int} */
    private function parentFromInput(array $input): array
    {
        if (($input['parent_type'] ?? '') !== '' && (int) ($input['parent_id'] ?? 0) > 0) {
            $type = (string) $input['parent_type'];
            if (! in_array($type, ['cliente', 'cpotencial'], true)) {
                throw new InvalidArgumentException('Tipo de cuenta invalido.');
            }
            return [$type, (int) $input['parent_id']];
        }
        $legacy = (string) ($input['cliente_id'] ?? '');
        if (str_contains($legacy, '_')) {
            [$id, $type] = explode('_', $legacy, 2);
            return [(int) $type === 1 ? 'cliente' : 'cpotencial', (int) $id];
        }
        if ((int) ($input['cliente_id'] ?? 0) > 0) {
            return ['cliente', (int) $input['cliente_id']];
        }
        if ((int) ($input['cpotencial_id'] ?? 0) > 0) {
            return ['cpotencial', (int) $input['cpotencial_id']];
        }
        throw new InvalidArgumentException('Selecciona una cuenta padre para el documento.');
    }

    private function validateUpload(UploadedFile $file): void
    {
        if ($file->getSize() > self::MAX_BYTES) {
            throw new InvalidArgumentException('El archivo excede el tamano maximo permitido.');
        }
        $extension = strtolower($file->getClientExtension());
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Tipo de archivo no permitido.');
        }
    }

    private function storeFile(UploadedFile $file): string
    {
        $extension = strtolower($file->getClientExtension());
        $name = bin2hex(random_bytes(16)) . '.' . $extension;
        $target = rtrim($this->storageRoot, DIRECTORY_SEPARATOR);
        if (! is_dir($target) && ! mkdir($target, 0775, true) && ! is_dir($target)) {
            throw new RuntimeException('No fue posible preparar el almacenamiento de documentos.');
        }
        if (is_uploaded_file($file->getTempName())) {
            $file->move($target, $name, true);
        } elseif (! copy($file->getTempName(), $target . DIRECTORY_SEPARATOR . $name)) {
            throw new RuntimeException('No fue posible almacenar el documento.');
        }
        return 'documents/' . $name;
    }

    private function safeClientName(string $name): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._ -]/', '_', basename($name));
        return trim((string) $clean) !== '' ? (string) $clean : 'documento';
    }
}

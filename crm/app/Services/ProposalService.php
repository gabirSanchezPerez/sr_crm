<?php

namespace App\Services;

use App\Models\ProposalModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

final class ProposalService
{
    public function __construct(
        private readonly ProposalModel $model = new ProposalModel(),
        private readonly DocumentService $documents = new DocumentService()
    ) {
    }

    /** @return list<array<string,mixed>> */
    public function rows(array $identity, string $scope, ?string $search = null): array
    {
        return $this->model->activeRows($identity, $scope, $search);
    }

    /** @return list<array<string,mixed>> */
    public function forParent(string $parentType, int $parentId, array $identity, string $scope, ?string $search = null): array
    {
        return $this->model->parentIsAccessible($parentType, $parentId, $identity, $scope)
            ? $this->model->activeByParent($parentType, $parentId, $identity, $scope, $search)
            : [];
    }

    public function find(int $id, array $identity, string $scope): ?array
    {
        return $this->model->activeById($id, $identity, $scope);
    }

    /** @return list<array{id:string,text:string}> */
    public function parentOptions(array $identity, string $scope, ?string $search = null): array
    {
        return $this->model->parentOptions($identity, $scope, $search);
    }

    /** @return array<int,string> */
    public function contactOptionsForInput(array $input, ?array $current = null): array
    {
        [$parentType, $parentId] = $this->parentFromInput($input, $current);
        return $this->model->contactOptions($parentType, $parentId);
    }

    /** @return array<int,string> */
    public function contactOptionsForAccessibleParent(string $combinedParent, array $identity, string $scope): array
    {
        if (! str_contains($combinedParent, '_')) { throw new InvalidArgumentException('Selecciona un cliente o prospecto.'); }
        [$id, $type] = explode('_', $combinedParent, 2);
        $parentId = (int) $id;
        $parentType = (int) $type === 1 ? 'cliente' : 'cpotencial';
        if ($parentId <= 0 || ! $this->model->parentIsAccessible($parentType, $parentId, $identity, $scope)) {
            throw new InvalidArgumentException('La cuenta no esta disponible para este usuario.');
        }
        return $this->model->contactOptions($parentType, $parentId);
    }

    /** @param array<string,mixed> $input @param list<UploadedFile> $files */
    public function create(array $input, array $files, array $identity, string $scope, int $actorId): int
    {
        $db = db_connect();
        $storedPaths = [];
        $db->transStart();
        try {
            $id = $this->createWithoutTransaction($input, $identity, $scope, $actorId);
            $storedPaths = $this->attachDocuments($id, $files, $identity, $scope, $actorId);
        } catch (\Throwable $exception) {
            $db->transRollback();
            $this->resetTransactionStatus($db);
            $this->cleanupStoredDocuments($storedPaths);
            throw $exception;
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            $this->resetTransactionStatus($db);
            $this->cleanupStoredDocuments($storedPaths);
            throw new RuntimeException('No fue posible crear la propuesta.');
        }
        return $id;
    }

    /** @param array<string,mixed> $input */
    public function createWithoutTransaction(array $input, array $identity, string $scope, int $actorId): int
    {
        $data = $this->payload($input);
        if ((int) ($data['ejecutivo_id'] ?? 0) <= 0) {
            $data['ejecutivo_id'] = $actorId;
        }
        $this->guardProposal($data, $identity, $scope);
        $data['u_crea'] = $actorId;
        $data['f_creacion'] = date('Y-m-d H:i:s');
        $data['deleted'] = 0;
        $id = $this->model->insert($data, true);
        if ($id === false) {
            throw new RuntimeException('No fue posible crear la propuesta.');
        }
        return (int) $id;
    }

    /** @param array<string,mixed> $input @param list<UploadedFile> $files */
    public function update(int $id, array $input, array $files, array $identity, string $scope, int $actorId): void
    {
        $record = $this->find($id, $identity, $scope);
        if ($record === null) {
            throw new RuntimeException('Propuesta no encontrada.');
        }
        $data = $this->payload($input, $record);
        $this->guardProposal($data, $identity, $scope);
        $data['u_modifica'] = $actorId;
        $data['f_modificacion'] = date('Y-m-d H:i:s');
        $db = db_connect();
        $storedPaths = [];
        $db->transStart();
        try {
            if ((int) ($record['estado_id'] ?? 0) !== 4 && (int) ($data['estado_id'] ?? 0) === 4) {
                (new ProposalPaymentService())->registerSale($id, $input, $identity, $scope, $actorId);
            }
            if (! $this->model->update($id, $data)) {
                throw new RuntimeException('No fue posible actualizar la propuesta.');
            }
            $storedPaths = $this->attachDocuments($id, $files, $identity, $scope, $actorId);
        } catch (\Throwable $exception) {
            $db->transRollback();
            $this->resetTransactionStatus($db);
            $this->cleanupStoredDocuments($storedPaths);
            throw $exception;
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            $this->resetTransactionStatus($db);
            $this->cleanupStoredDocuments($storedPaths);
            throw new RuntimeException('No fue posible actualizar la propuesta.');
        }
    }

    public function softDelete(int $id, array $identity, string $scope, int $actorId): void
    {
        if ($this->find($id, $identity, $scope) === null) {
            throw new RuntimeException('Propuesta no encontrada.');
        }
        if (! $this->model->update($id, ['deleted' => 1, 'u_modifica' => $actorId, 'f_modificacion' => date('Y-m-d H:i:s')])) {
            throw new RuntimeException('No fue posible desactivar la propuesta.');
        }
    }

    /** @return list<array<string,mixed>> */
    public function documents(int $proposalId, array $identity, string $scope): array
    {
        if ($this->find($proposalId, $identity, $scope) === null) {
            return [];
        }
        return $this->documents->forProposal($proposalId, $identity, $scope);
    }

    /** @return list<array<string,mixed>> */
    public function payments(int $proposalId): array
    {
        return (new ProposalPaymentService())->forProposal($proposalId);
    }

    /** @param list<UploadedFile> $files @return list<string> */
    public function attachDocuments(int $proposalId, array $files, array $identity, string $scope, int $actorId): array
    {
        $storedPaths = [];
        foreach ($files as $file) {
            if ($file->getError() === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            try {
                $documentId = $this->documents->createForProposal($proposalId, [], $file, $identity, $scope, $actorId);
                $document = db_connect()->table('documento')->select('archivo_ruta')->where('id', $documentId)->get()->getRowArray();
                if ($document !== null && (string) ($document['archivo_ruta'] ?? '') !== '') {
                    $storedPaths[] = (string) $document['archivo_ruta'];
                }
            } catch (\Throwable $exception) {
                $this->cleanupStoredDocuments($storedPaths);
                throw $exception;
            }
        }

        return $storedPaths;
    }

    public function activityThreeIsProposalDelivery(): bool
    {
        return $this->model->activityThreeIsProposalDelivery();
    }

    /** @param array<string,mixed> $data */
    public function proposalMatchesFollowUpParent(int $proposalId, array $data, array $identity, string $scope): bool
    {
        $proposal = $this->find($proposalId, $identity, $scope);
        if ($proposal === null) {
            return false;
        }
        if ((int) ($data['tipo_id'] ?? 0) === 1) {
            return (int) ($proposal['cliente_id'] ?? 0) === (int) ($data['cliente_id'] ?? 0);
        }
        return (int) ($proposal['cpotencial_id'] ?? 0) === (int) ($data['cliente_id'] ?? 0);
    }

    /** @param array<string,mixed>|null $current @return array<string,mixed> */
    private function payload(array $input, ?array $current = null): array
    {
        [$parentType, $parentId] = $this->parentFromInput($input, $current);
        return [
            'nombre' => trim((string) ($input['nombre'] ?? $input['propuesta_nombre'] ?? $current['nombre'] ?? '')),
            'canal_id' => (int) ($input['canal_id'] ?? $input['propuesta_canal_id'] ?? $current['canal_id'] ?? 0),
            'monto' => (float) ($input['monto'] ?? $input['propuesta_monto'] ?? $current['monto'] ?? 0),
            'cliente_id' => $parentType === 'cliente' ? $parentId : null,
            'cpotencial_id' => $parentType === 'cpotencial' ? $parentId : null,
            'contacto_id' => (int) ($input['contacto_id'] ?? $input['propuesta_contacto_id'] ?? $current['contacto_id'] ?? 0),
            'estado_id' => (int) ($input['estado_id'] ?? $input['propuesta_estado_id'] ?? $current['estado_id'] ?? 0),
            'ejecutivo_id' => (int) ($input['ejecutivo_id'] ?? $current['ejecutivo_id'] ?? ($input['actor_id'] ?? 0)),
            'descripcion' => trim((string) ($input['descripcion'] ?? $input['propuesta_descripcion'] ?? $current['descripcion'] ?? '')) ?: null,
        ];
    }

    /** @param array<string,mixed>|null $current @return array{0:string,1:int} */
    private function parentFromInput(array $input, ?array $current = null): array
    {
        $combined = (string) ($input['cliente_id'] ?? $input['propuesta_cliente_id'] ?? '');
        if (str_contains($combined, '_')) {
            [$id, $type] = explode('_', $combined, 2);
            return [(int) $type === 1 ? 'cliente' : 'cpotencial', (int) $id];
        }
        if (($input['parent_type'] ?? '') !== '' && (int) ($input['parent_id'] ?? 0) > 0) {
            $type = (string) $input['parent_type'];
            if (! in_array($type, ['cliente', 'cpotencial'], true)) {
                throw new InvalidArgumentException('Tipo de cuenta invalido.');
            }
            return [$type, (int) $input['parent_id']];
        }
        if ($current !== null) {
            if ((int) ($current['cliente_id'] ?? 0) > 0) {
                return ['cliente', (int) $current['cliente_id']];
            }
            return ['cpotencial', (int) $current['cpotencial_id']];
        }
        throw new InvalidArgumentException('Selecciona un cliente o prospecto para la propuesta.');
    }

    /** @param array<string,mixed> $data */
    private function guardProposal(array $data, array $identity, string $scope): void
    {
        $parentType = (int) ($data['cliente_id'] ?? 0) > 0 ? 'cliente' : 'cpotencial';
        $parentId = (int) ($data['cliente_id'] ?? $data['cpotencial_id'] ?? 0);
        if ($data['nombre'] === '') {
            throw new InvalidArgumentException('Indica el nombre de la propuesta.');
        }
        if ($parentId <= 0 || ! $this->model->parentIsAccessible($parentType, $parentId, $identity, $scope)) {
            throw new InvalidArgumentException('La cuenta padre no esta disponible para este usuario.');
        }
        if (! $this->model->contactBelongsToParent((int) $data['contacto_id'], $parentType, $parentId)) {
            throw new InvalidArgumentException('El contacto no pertenece a la cuenta seleccionada.');
        }
        foreach (['cgestion' => 'canal_id', 'estado' => 'estado_id', 'usuario' => 'ejecutivo_id'] as $table => $field) {
            if (! $this->activeIdExists($table, (int) $data[$field])) {
                throw new InvalidArgumentException('Referencia invalida para la propuesta.');
            }
        }
    }

    private function activeIdExists(string $table, int $id): bool
    {
        return $id > 0 && db_connect()->table($table)->where('id', $id)->where('deleted', 0)->countAllResults() > 0;
    }

    /** @param list<string> $paths */
    private function cleanupStoredDocuments(array $paths): void
    {
        foreach ($paths as $path) {
            $this->documents->removeStoredFile($path);
        }
    }

    private function resetTransactionStatus(object $db): void
    {
        if (method_exists($db, 'resetTransStatus')) {
            $db->resetTransStatus();
        }
    }

}

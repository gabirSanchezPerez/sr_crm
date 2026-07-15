<?php

namespace App\Services;

use App\Models\FollowUpModel;
use CodeIgniter\HTTP\Files\UploadedFile;
use InvalidArgumentException;
use RuntimeException;

final class FollowUpService
{
    public function __construct(private readonly FollowUpModel $model = new FollowUpModel()) {}

    /** @return list<array<string,mixed>> */
    public function rows(array $identity, string $scope, ?string $search = null): array { return $this->model->activeRows($identity, $scope, $search); }
    /** @return list<array<string,mixed>> */
    public function forParent(string $parentType, int $parentId, array $identity, string $scope, ?string $search = null): array { return $this->model->parentIsAccessible($parentType, $parentId, $identity, $scope) ? $this->model->activeByParent($parentType, $parentId, $identity, $scope, $search) : []; }
    /** @return list<array<string,mixed>> */
    public function forProposal(int $proposalId, array $identity, string $scope, ?string $search = null): array { return $this->model->activeByProposal($proposalId, $identity, $scope, $search); }
    public function find(int $id, array $identity, string $scope): ?array { return $this->model->activeById($id, $identity, $scope); }
    /** @return list<array{id:string,text:string}> */
    public function parentOptions(array $identity, string $scope, ?string $search = null): array { return $this->model->parentOptions($identity, $scope, $search); }
    public function parentIsAccessible(string $parentType, int $parentId, array $identity, string $scope): bool { return $this->model->parentIsAccessible($parentType, $parentId, $identity, $scope); }

    /** @param array<string,mixed> $input */
    /** @param list<UploadedFile> $proposalDocuments */
    public function create(array $input, array $identity, string $scope, int $actorId, array $proposalDocuments = []): int
    {
        $data = $this->payload($input, true);
        $this->guardReferences($data);
        $this->guardParent($data, $identity, $scope);
        $proposalService = new ProposalService();
        if ((int) ($data['propuesta_id'] ?? 0) > 0 && ! $proposalService->proposalMatchesFollowUpParent((int) $data['propuesta_id'], $data, $identity, $scope)) {
            throw new InvalidArgumentException('La propuesta no corresponde a la cuenta del seguimiento.');
        }
        $data['u_crea'] = $actorId; $data['f_creacion'] = date('Y-m-d H:i:s'); $data['deleted'] = 0;
        $db = db_connect();
        $storedProposalDocuments = [];
        $db->transStart();
        try {
            if ((int) $data['actividad_id'] === 3 && (int) ($data['propuesta_id'] ?? 0) <= 0) {
                $proposalInput = $input + [
                    'propuesta_cliente_id' => (string) ($input['cliente_id'] ?? ''),
                    'actor_id' => $actorId,
                    'ejecutivo_id' => (int) $data['ejecutivo_id'],
                ];
                $data['propuesta_id'] = $proposalService->createWithoutTransaction($proposalInput, $identity, $scope, $actorId);
                $storedProposalDocuments = $proposalService->attachDocuments((int) $data['propuesta_id'], $proposalDocuments, $identity, $scope, $actorId);
            }
            $data = $this->schemaPayload($data);
            $id = $this->model->insert($data, true);
            if ($id === false) { throw new RuntimeException('No fue posible crear el seguimiento.'); }
        } catch (\Throwable $exception) {
            $db->transRollback();
            $this->resetTransactionStatus($db);
            $this->cleanupProposalDocuments($storedProposalDocuments);
            throw $exception;
        }
        $db->transComplete();
        if (! $db->transStatus()) {
            $this->resetTransactionStatus($db);
            $this->cleanupProposalDocuments($storedProposalDocuments);
            throw new RuntimeException('No fue posible crear el seguimiento.');
        }
        return (int) $id;
    }

    /** @param array<string,mixed> $input */
    public function update(int $id, array $input, array $identity, string $scope, int $actorId): void
    {
        $record = $this->find($id, $identity, $scope);
        if ($record === null) { throw new RuntimeException('Seguimiento no encontrado.'); }
        $data = $this->payload($input, false, $record);
        $this->guardReferences($data);
        $this->guardParent($data, $identity, $scope);
        if ((int) ($data['propuesta_id'] ?? 0) > 0 && ! (new ProposalService())->proposalMatchesFollowUpParent((int) $data['propuesta_id'], $data, $identity, $scope)) {
            throw new InvalidArgumentException('La propuesta no corresponde a la cuenta del seguimiento.');
        }
        $data['u_modifica'] = $actorId; $data['f_modificacion'] = date('Y-m-d H:i:s');
        if (! $this->model->update($id, $this->schemaPayload($data))) { throw new RuntimeException('No fue posible actualizar el seguimiento.'); }
    }

    public function softDelete(int $id, array $identity, string $scope, int $actorId): void
    {
        if ($this->find($id, $identity, $scope) === null) { throw new RuntimeException('Seguimiento no encontrado.'); }
        if (! $this->model->update($id, ['deleted' => 1, 'u_modifica' => $actorId, 'f_modificacion' => date('Y-m-d H:i:s')])) { throw new RuntimeException('No fue posible desactivar el seguimiento.'); }
    }

    /** @param array<string,mixed> $input @param array<string,mixed>|null $current @return array<string,mixed> */
    private function payload(array $input, bool $isNew, ?array $current = null): array
    {
        [$parentType, $parentId, $tipoId] = $this->parentFromInput($input, $current);
        return [
            'fecha' => (string) ($input['fecha'] ?? $current['fecha'] ?? ''),
            'hora' => (string) ($input['hora'] ?? $current['hora'] ?? ''),
            'actividad_id' => (int) ($input['actividad_id'] ?? 0),
            'descripcion' => trim((string) ($input['descripcion'] ?? '')),
            'adjunto' => trim((string) ($input['adjunto'] ?? '')) ?: null,
            'estado_id' => (int) ($input['estado_id'] ?? 0),
            'cliente_id' => $parentId,
            'tipo_id' => $tipoId,
            'propuesta_id' => ($input['propuesta_id'] ?? $current['propuesta_id'] ?? '') === '' ? null : (int) ($input['propuesta_id'] ?? $current['propuesta_id'] ?? 0),
            'ejecutivo_id' => (int) ($input['ejecutivo_id'] ?? 0),
            'monto' => ($input['monto'] ?? '') === '' ? null : (float) $input['monto'],
        ];
    }

    /** @param array<string,mixed>|null $current @return array{0:string,1:int,2:int} */
    private function parentFromInput(array $input, ?array $current = null): array
    {
        $combined = (string) ($input['cliente_id'] ?? '');
        if (str_contains($combined, '_')) { [$id, $type] = explode('_', $combined, 2); $tipo = (int) $type === 1 ? 1 : 2; return [$tipo === 1 ? 'cliente' : 'cpotencial', (int) $id, $tipo]; }
        if (($input['parent_type'] ?? '') !== '' && (int) ($input['parent_id'] ?? 0) > 0) { $type = (string) $input['parent_type']; if (! in_array($type, ['cliente','cpotencial'], true)) { throw new InvalidArgumentException('Tipo de cuenta invalido.'); } return [$type, (int) $input['parent_id'], $type === 'cliente' ? 1 : 2]; }
        if ($current !== null) { $tipo = (int) $current['tipo_id']; return [$tipo === 1 ? 'cliente' : 'cpotencial', (int) $current['cliente_id'], $tipo]; }
        throw new InvalidArgumentException('Selecciona una cuenta padre para el seguimiento.');
    }

    /** @param array<string,mixed> $data */
    private function guardReferences(array $data): void
    {
        if (! $this->activeIdExists('actividad', (int) $data['actividad_id'])) {
            throw new InvalidArgumentException('La actividad seleccionada no esta disponible.');
        }
        if (! $this->activeIdExists('estado', (int) $data['estado_id'])) {
            throw new InvalidArgumentException('El estado seleccionado no esta disponible.');
        }
        if (! $this->activeIdExists('usuario', (int) $data['ejecutivo_id'])) {
            throw new InvalidArgumentException('El ejecutivo seleccionado no esta disponible.');
        }
    }

    private function activeIdExists(string $table, int $id): bool
    {
        if ($id <= 0) {
            return false;
        }

        return db_connect()->table($table)
            ->where('id', $id)
            ->where('deleted', 0)
            ->countAllResults() > 0;
    }

    /** @param array<string,mixed> $data */
    private function guardParent(array $data, array $identity, string $scope): void
    {
        $parentType = (int) $data['tipo_id'] === 1 ? 'cliente' : 'cpotencial';
        if ((int) $data['cliente_id'] <= 0 || ! $this->model->parentIsAccessible($parentType, (int) $data['cliente_id'], $identity, $scope)) { throw new InvalidArgumentException('La cuenta padre no esta disponible para este usuario.'); }
    }

    /** @param array<string,mixed> $data @return array<string,mixed> */
    private function schemaPayload(array $data): array
    {
        if (! db_connect()->fieldExists('propuesta_id', 'seguimiento')) {
            unset($data['propuesta_id']);
        }
        return $data;
    }

    /** @param list<string> $paths */
    private function cleanupProposalDocuments(array $paths): void
    {
        $documents = new DocumentService();
        foreach ($paths as $path) {
            $documents->removeStoredFile($path);
        }
    }

    private function resetTransactionStatus(object $db): void
    {
        if (method_exists($db, 'resetTransStatus')) {
            $db->resetTransStatus();
        }
    }
}

<?php

namespace App\Services;

use App\Models\CustomerModel;
use App\Models\UserModel;
use RuntimeException;

final class CustomerService
{
    public function __construct(
        private readonly CustomerModel $model = new CustomerModel(),
        private readonly UserModel $users = new UserModel(),
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(array $identity, string $scope, ?string $search = null): array
    {
        return $this->model->activeRows($identity, $scope, $search);
    }

    public function find(int $id, array $identity, string $scope): ?array
    {
        return $this->model->activeById($id, $identity, $scope);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input, int $actorId): int
    {
        $data = $this->payload($input);
        $data['cgestion_id'] = $this->managementIdForExecutive($data['ejecutivo_id']);
        $this->guardDuplicate($data['cgestion_id'], $data['razon_social'], $data['marca']);

        $data['cpotencial_id'] = $data['cpotencial_id'] ?: null;
        $data['deleted'] = 0;
        $data['_countries_id'] = (int) ($data['_countries_id'] ?? 42);
        $data['u_crea'] = $actorId;
        $data['f_creacion'] = date('Y-m-d H:i:s');

        $id = $this->model->insert($data, true);
        if ($id === false) {
            throw new RuntimeException('No fue posible crear el cliente.');
        }

        return (int) $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $id, array $input, int $actorId): void
    {
        $data = $this->payload($input);
        $data['cgestion_id'] = $this->managementIdForExecutive($data['ejecutivo_id']);
        $this->guardDuplicate($data['cgestion_id'], $data['razon_social'], $data['marca'], $id);

        $data['cpotencial_id'] = $data['cpotencial_id'] ?: null;
        $data['u_modifica'] = $actorId;
        $data['f_modificacion'] = date('Y-m-d H:i:s');

        if (! $this->model->update($id, $data)) {
            throw new RuntimeException('No fue posible actualizar el cliente.');
        }
    }

    public function softDelete(int $id, int $actorId): void
    {
        if (! $this->model->update($id, [
            'deleted' => 1,
            'u_modifica' => $actorId,
            'f_modificacion' => date('Y-m-d H:i:s'),
        ])) {
            throw new RuntimeException('No fue posible desactivar el cliente.');
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function payload(array $input): array
    {
        return [
            'razon_social' => trim((string) ($input['razon_social'] ?? '')),
            'marca' => trim((string) ($input['marca'] ?? '')),
            'rfc' => strtoupper(trim((string) ($input['rfc'] ?? ''))),
            'sector_id' => (int) ($input['sector_id'] ?? 0),
            'cpotencial_id' => (int) ($input['cpotencial_id'] ?? 0),
            'ejecutivo_id' => (int) ($input['ejecutivo_id'] ?? 0),
            '_countries_id' => (int) ($input['_countries_id'] ?? 42),
            'estado' => trim((string) ($input['estado'] ?? '')),
            'ciudad' => trim((string) ($input['ciudad'] ?? '')),
            'cp' => trim((string) ($input['cp'] ?? '')),
            'direccion' => trim((string) ($input['direccion'] ?? '')),
        ];
    }

    private function managementIdForExecutive(int $executiveId): int
    {
        $user = $this->users->findActiveById($executiveId);
        if ($user === null) {
            throw new RuntimeException('El ejecutivo seleccionado no existe.');
        }

        return (int) $user['cgestion_id'];
    }

    private function guardDuplicate(int $managementId, string $businessName, string $brand, ?int $exceptId = null): void
    {
        if ($this->model->duplicateExists($managementId, $businessName, $brand, $exceptId)) {
            throw new RuntimeException('Ya existe un cliente activo con la misma gestion, razon social y marca.');
        }
    }
}

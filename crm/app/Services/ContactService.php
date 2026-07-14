<?php

namespace App\Services;

use App\Models\ContactModel;
use InvalidArgumentException;
use RuntimeException;

final class ContactService
{
    public function __construct(private readonly ContactModel $model = new ContactModel())
    {
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

    /** @param array<string, mixed> $input */
    public function create(array $input, array $identity, string $scope, int $actorId): int
    {
        $data = $this->payload($input);
        $this->guardAccessibleParent($data, $identity, $scope);

        $data['deleted'] = 0;
        $data['_countries_id'] = (int) ($data['_countries_id'] ?? 42);
        $data['u_crea'] = $actorId;
        $data['f_creacion'] = date('Y-m-d H:i:s');

        $id = $this->model->insert($data, true);
        if ($id === false) {
            throw new RuntimeException('No fue posible crear el contacto.');
        }

        return (int) $id;
    }

    /** @param array<string, mixed> $input */
    public function update(int $id, array $input, array $identity, string $scope, int $actorId): void
    {
        if ($this->find($id, $identity, $scope) === null) {
            throw new RuntimeException('Contacto no encontrado.');
        }

        $data = $this->payload($input);
        $this->guardAccessibleParent($data, $identity, $scope);
        $data['u_modifica'] = $actorId;
        $data['f_modificacion'] = date('Y-m-d H:i:s');

        if (! $this->model->update($id, $data)) {
            throw new RuntimeException('No fue posible actualizar el contacto.');
        }
    }

    public function softDelete(int $id, array $identity, string $scope, int $actorId): void
    {
        if ($this->find($id, $identity, $scope) === null) {
            throw new RuntimeException('Contacto no encontrado.');
        }

        if (! $this->model->update($id, [
            'deleted' => 1,
            'u_modifica' => $actorId,
            'f_modificacion' => date('Y-m-d H:i:s'),
        ])) {
            throw new RuntimeException('No fue posible desactivar el contacto.');
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function payload(array $input): array
    {
        [$parentType, $parentId] = $this->parentFromInput($input);

        return [
            'cliente_id' => $parentType === 'cliente' ? $parentId : null,
            'cpotencial_id' => $parentType === 'cpotencial' ? $parentId : null,
            'nombre' => trim((string) ($input['nombre'] ?? '')),
            'telefono' => trim((string) ($input['telefono'] ?? '')),
            'celular' => trim((string) ($input['celular'] ?? '')),
            'otro_num' => trim((string) ($input['otro_num'] ?? '')),
            'puesto' => trim((string) ($input['puesto'] ?? '')),
            'departamento' => trim((string) ($input['departamento'] ?? '')),
            'correo' => strtolower(trim((string) ($input['correo'] ?? ''))),
            'descripcion' => trim((string) ($input['descripcion'] ?? '')),
            '_countries_id' => (int) ($input['_countries_id'] ?? 42),
            'estado' => trim((string) ($input['estado'] ?? '')),
            'ciudad' => trim((string) ($input['ciudad'] ?? '')),
            'cp' => trim((string) ($input['cp'] ?? '')),
            'direccion' => trim((string) ($input['direccion'] ?? '')),
        ];
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

        throw new InvalidArgumentException('Selecciona una cuenta padre para el contacto.');
    }

    /** @param array<string, mixed> $data */
    private function guardAccessibleParent(array $data, array $identity, string $scope): void
    {
        $parentType = (int) ($data['cliente_id'] ?? 0) > 0 ? 'cliente' : 'cpotencial';
        $parentId = (int) ($data['cliente_id'] ?? 0) > 0 ? (int) $data['cliente_id'] : (int) $data['cpotencial_id'];

        if ($parentId <= 0 || ! $this->model->parentIsAccessible($parentType, $parentId, $identity, $scope)) {
            throw new InvalidArgumentException('La cuenta padre no esta disponible para este usuario.');
        }
    }
}
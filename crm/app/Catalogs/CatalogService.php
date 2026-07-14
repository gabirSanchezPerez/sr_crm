<?php

namespace App\Catalogs;

use RuntimeException;

final class CatalogService
{
    public function __construct(private readonly CatalogModel $model)
    {
    }

    public function model(): CatalogModel
    {
        return $this->model;
    }

    public function definition(): CatalogDefinition
    {
        return $this->model->definition();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function rows(?string $search = null): array
    {
        return $this->model->activeRows($search);
    }

    public function find(int $id): ?array
    {
        return $this->model->activeById($id);
    }

    /**
     * @param array<string, mixed> $input
     */
    public function create(array $input, int $actorId): int
    {
        $data = $this->payload($input);
        $data['deleted'] = 0;
        $data['u_crea'] = $actorId;
        $data['f_creacion'] = date('Y-m-d H:i:s');

        $id = $this->model->insert($data, true);
        if ($id === false) {
            throw new RuntimeException('No fue posible crear el registro.');
        }

        return (int) $id;
    }

    /**
     * @param array<string, mixed> $input
     */
    public function update(int $id, array $input, int $actorId): void
    {
        $data = $this->payload($input);
        $data['u_modifica'] = $actorId;
        $data['f_modificacion'] = date('Y-m-d H:i:s');

        if (! $this->model->update($id, $data)) {
            throw new RuntimeException('No fue posible actualizar el registro.');
        }
    }

    public function softDelete(int $id, int $actorId): void
    {
        if (! $this->definition()->supportsDelete) {
            throw new RuntimeException('Este catalogo no permite eliminacion.');
        }

        if (! $this->model->update($id, [
            'deleted' => 1,
            'u_modifica' => $actorId,
            'f_modificacion' => date('Y-m-d H:i:s'),
        ])) {
            throw new RuntimeException('No fue posible desactivar el registro.');
        }
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function payload(array $input): array
    {
        $data = [];
        foreach ($this->definition()->fields as $field) {
            $value = $input[$field->name] ?? null;
            $data[$field->name] = is_string($value) ? trim($value) : $value;
        }

        return $data;
    }
}

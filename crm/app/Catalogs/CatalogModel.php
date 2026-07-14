<?php

namespace App\Catalogs;

use CodeIgniter\Model;

final class CatalogModel extends Model
{
    private CatalogDefinition $definition;

    public function __construct(CatalogDefinition $definition)
    {
        parent::__construct();

        $this->definition = $definition;
        $this->table = $definition->table;
        $this->primaryKey = 'id';
        $this->returnType = 'array';
        $this->useTimestamps = false;
        $this->allowedFields = array_merge($definition->fieldNames(), [
            'deleted',
            'u_crea',
            'f_creacion',
            'u_modifica',
            'f_modificacion',
        ]);
    }

    public function definition(): CatalogDefinition
    {
        return $this->definition;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeRows(?string $search = null): array
    {
        $builder = $this->builder()
            ->where($this->table . '.deleted', 0)
            ->orderBy($this->definition->orderBy, 'ASC');

        if ($search !== null && $search !== '') {
            $builder->groupStart();
            foreach ($this->definition->listFields() as $index => $field) {
                $index === 0
                    ? $builder->like($field->name, $search)
                    : $builder->orLike($field->name, $search);
            }
            $builder->groupEnd();
        }

        return $builder->get()->getResultArray();
    }

    public function activeById(int $id): ?array
    {
        return $this->where('id', $id)->where('deleted', 0)->first();
    }
}

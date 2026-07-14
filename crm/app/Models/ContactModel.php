<?php

namespace App\Models;

use CodeIgniter\Model;

final class ContactModel extends Model
{
    protected $table = 'contacto';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'cliente_id', 'cpotencial_id', 'nombre', 'telefono', 'celular', 'otro_num',
        'puesto', 'departamento', 'correo', 'descripcion', 'u_crea', 'u_modifica',
        'f_creacion', 'f_modificacion', 'deleted', '_countries_id', 'estado',
        'ciudad', 'cp', 'direccion',
    ];
    protected $useTimestamps = false;

    /**
     * @return list<array<string, mixed>>
     */
    public function activeRows(array $identity, string $scope, ?string $search = null): array
    {
        $builder = $this->baseBuilder()
            ->where('co.deleted', 0)
            ->groupStart()
                ->where('cl.id IS NOT', null)
                ->orWhere('cp.id IS NOT', null)
            ->groupEnd()
            ->orderBy('co.id', 'DESC');

        $this->applyParentScope($builder, $identity, $scope);
        $this->applySearch($builder, $search);

        return $builder->get()->getResultArray();
    }

    public function activeById(int $id, array $identity, string $scope): ?array
    {
        $builder = $this->baseBuilder()
            ->where('co.id', $id)
            ->where('co.deleted', 0)
            ->groupStart()
                ->where('cl.id IS NOT', null)
                ->orWhere('cp.id IS NOT', null)
            ->groupEnd();

        $this->applyParentScope($builder, $identity, $scope);

        return $builder->get()->getRowArray();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function activeByParent(string $parentType, int $parentId, array $identity, string $scope, ?string $search = null): array
    {
        $builder = $this->baseBuilder()
            ->where('co.deleted', 0)
            ->orderBy('co.nombre', 'ASC');

        if ($parentType === 'cliente') {
            $builder->where('co.cliente_id', $parentId)->where('co.cpotencial_id', null);
        } elseif ($parentType === 'cpotencial') {
            $builder->where('co.cpotencial_id', $parentId)->where('co.cliente_id', null);
        } else {
            $builder->where('1 =', 0, false);
        }

        $this->applyParentScope($builder, $identity, $scope);
        $this->applySearch($builder, $search);

        return $builder->get()->getResultArray();
    }

    public function parentIsAccessible(string $parentType, int $parentId, array $identity, string $scope): bool
    {
        $builder = $this->db->table($parentType === 'cliente' ? 'cliente p' : 'cpotencial p')
            ->select('p.id')
            ->join('usuario_ucomercial uuc', 'uuc.usuario_id = p.ejecutivo_id AND uuc.deleted = 0', 'left')
            ->where('p.id', $parentId)
            ->where('p.deleted', 0);

        if ($parentType === 'cpotencial') {
            $builder->where('p.cliente_id', null);
        } elseif ($parentType !== 'cliente') {
            return false;
        }

        if ($scope === 'owner') {
            $builder->where('p.ejecutivo_id', (int) ($identity['user_id'] ?? 0));
        } elseif ($scope === 'team') {
            $builder->where('uuc.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0));
        } elseif ($scope !== 'all') {
            return false;
        }

        return $builder->get()->getRowArray() !== null;
    }

    private function baseBuilder(): object
    {
        return $this->db->table($this->table . ' co')
            ->select('co.*, cl.razon_social AS cliente, cl.marca AS cliente_marca, cp.razon_social AS cpotencial, cp.marca AS cpotencial_marca, uc.nombre AS creador')
            ->join('cliente cl', 'co.cliente_id = cl.id AND cl.deleted = 0', 'left')
            ->join('cpotencial cp', 'co.cpotencial_id = cp.id AND cp.deleted = 0 AND cp.cliente_id IS NULL', 'left')
            ->join('usuario uc', 'co.u_crea = uc.id', 'left')
            ->join('usuario_ucomercial uuc_cl', 'uuc_cl.usuario_id = cl.ejecutivo_id AND uuc_cl.deleted = 0', 'left')
            ->join('usuario_ucomercial uuc_cp', 'uuc_cp.usuario_id = cp.ejecutivo_id AND uuc_cp.deleted = 0', 'left');
    }

    private function applyParentScope(object $builder, array $identity, string $scope): void
    {
        if ($scope === 'owner') {
            $builder->groupStart()
                ->where('cl.ejecutivo_id', (int) ($identity['user_id'] ?? 0))
                ->orWhere('cp.ejecutivo_id', (int) ($identity['user_id'] ?? 0))
                ->groupEnd();
            return;
        }

        if ($scope === 'team') {
            $builder->groupStart()
                ->where('uuc_cl.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0))
                ->orWhere('uuc_cp.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0))
                ->groupEnd();
            return;
        }

        if ($scope !== 'all') {
            $builder->where('1 =', 0, false);
        }
    }

    private function applySearch(object $builder, ?string $search): void
    {
        if ($search === null || $search === '') {
            return;
        }

        $builder->groupStart()
            ->like('co.nombre', $search)
            ->orLike('co.correo', $search)
            ->orLike('co.telefono', $search)
            ->orLike('co.celular', $search)
            ->orLike('cl.razon_social', $search)
            ->orLike('cp.razon_social', $search)
            ->groupEnd();
    }
}
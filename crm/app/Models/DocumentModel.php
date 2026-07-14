<?php

namespace App\Models;

use CodeIgniter\Model;

final class DocumentModel extends Model
{
    protected $table = 'documento';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'cliente_id', 'cpotencial_id', 'nombre', 'archivo_original', 'archivo_ruta',
        'mime', 'tamano', 'u_crea', 'u_modifica', 'f_creacion', 'f_modificacion', 'deleted',
    ];
    protected $useTimestamps = false;

    /** @return list<array<string, mixed>> */
    public function activeRows(array $identity, string $scope, ?string $search = null): array
    {
        $builder = $this->baseBuilder()
            ->where('d.deleted', 0)
            ->groupStart()
                ->where('cl.id IS NOT', null)
                ->orWhere('cp.id IS NOT', null)
            ->groupEnd()
            ->orderBy('d.id', 'DESC');
        $this->applyParentScope($builder, $identity, $scope);
        $this->applySearch($builder, $search);
        return $builder->get()->getResultArray();
    }

    public function activeById(int $id, array $identity, string $scope): ?array
    {
        $builder = $this->baseBuilder()
            ->where('d.id', $id)
            ->where('d.deleted', 0)
            ->groupStart()
                ->where('cl.id IS NOT', null)
                ->orWhere('cp.id IS NOT', null)
            ->groupEnd();
        $this->applyParentScope($builder, $identity, $scope);
        return $builder->get()->getRowArray();
    }

    /** @return list<array<string, mixed>> */
    public function activeByParent(string $parentType, int $parentId, array $identity, string $scope, ?string $search = null): array
    {
        $builder = $this->baseBuilder()->where('d.deleted', 0)->orderBy('d.nombre', 'ASC');
        if ($parentType === 'cliente') {
            $builder->where('d.cliente_id', $parentId)->where('d.cpotencial_id', null);
        } elseif ($parentType === 'cpotencial') {
            $builder->where('d.cpotencial_id', $parentId)->where('d.cliente_id', null);
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
        return $this->db->table($this->table . ' d')
            ->select('d.*, cl.razon_social AS cliente, cl.marca AS cliente_marca, cp.razon_social AS cpotencial, cp.marca AS cpotencial_marca, uc.nombre AS creador')
            ->join('cliente cl', 'd.cliente_id = cl.id AND cl.deleted = 0', 'left')
            ->join('cpotencial cp', 'd.cpotencial_id = cp.id AND cp.deleted = 0 AND cp.cliente_id IS NULL', 'left')
            ->join('usuario uc', 'd.u_crea = uc.id', 'left')
            ->join('usuario_ucomercial uuc_cl', 'uuc_cl.usuario_id = cl.ejecutivo_id AND uuc_cl.deleted = 0', 'left')
            ->join('usuario_ucomercial uuc_cp', 'uuc_cp.usuario_id = cp.ejecutivo_id AND uuc_cp.deleted = 0', 'left');
    }

    private function applyParentScope(object $builder, array $identity, string $scope): void
    {
        if ($scope === 'owner') {
            $builder->groupStart()->where('cl.ejecutivo_id', (int) ($identity['user_id'] ?? 0))->orWhere('cp.ejecutivo_id', (int) ($identity['user_id'] ?? 0))->groupEnd();
            return;
        }
        if ($scope === 'team') {
            $builder->groupStart()->where('uuc_cl.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0))->orWhere('uuc_cp.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0))->groupEnd();
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
            ->like('d.nombre', $search)
            ->orLike('d.archivo_original', $search)
            ->orLike('cl.razon_social', $search)
            ->orLike('cp.razon_social', $search)
            ->groupEnd();
    }
}
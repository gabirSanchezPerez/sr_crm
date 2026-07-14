<?php

namespace App\Models;

use CodeIgniter\Model;

final class CustomerModel extends Model
{
    protected $table = 'cliente';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'razon_social', 'rfc', 'sector_id', 'cpotencial_id', 'marca', 'cgestion_id',
        'ejecutivo_id', 'u_crea', 'u_modifica', 'f_creacion', 'f_modificacion',
        'deleted', '_countries_id', 'estado', 'ciudad', 'cp', 'direccion',
    ];
    protected $useTimestamps = false;

    /**
     * @return list<array<string, mixed>>
     */
    public function activeRows(array $identity, string $scope, ?string $search = null): array
    {
        $builder = $this->baseBuilder()
            ->where('c.deleted', 0)
            ->orderBy('c.id', 'DESC');

        $this->applyScope($builder, $identity, $scope);
        $this->applySearch($builder, $search);

        return $builder->get()->getResultArray();
    }

    public function activeById(int $id, array $identity, string $scope): ?array
    {
        $builder = $this->baseBuilder()
            ->where('c.id', $id)
            ->where('c.deleted', 0);

        $this->applyScope($builder, $identity, $scope);

        return $builder->get()->getRowArray();
    }

    public function duplicateExists(int $managementId, string $businessName, string $brand, ?int $exceptId = null): bool
    {
        $builder = $this->builder()
            ->where('deleted', 0)
            ->where('cgestion_id', $managementId)
            ->where('razon_social', $businessName)
            ->where('marca', $brand);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    private function baseBuilder(): object
    {
        return $this->db->table($this->table . ' c')
            ->select('c.*, s.nombre AS sector, cg.nombre AS cgestion, u.nombre AS ejecutivo, uuc.ucomercial_id')
            ->join('sector s', 'c.sector_id = s.id', 'left')
            ->join('cgestion cg', 'c.cgestion_id = cg.id', 'left')
            ->join('usuario u', 'c.ejecutivo_id = u.id', 'left')
            ->join('usuario_ucomercial uuc', 'uuc.usuario_id = u.id AND uuc.deleted = 0', 'left')
            ->groupBy('c.id, c.razon_social, c.rfc, c.sector_id, c.cpotencial_id, c.marca, c.cgestion_id, c.ejecutivo_id, c.u_crea, c.u_modifica, c.f_creacion, c.f_modificacion, c.deleted, c._countries_id, c.estado, c.ciudad, c.cp, c.direccion, s.nombre, cg.nombre, u.nombre, uuc.ucomercial_id');
    }

    private function applyScope(object $builder, array $identity, string $scope): void
    {
        if ($scope === 'owner') {
            $builder->where('c.ejecutivo_id', (int) ($identity['user_id'] ?? 0));
            return;
        }

        if ($scope === 'team') {
            $builder->where('uuc.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0));
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
            ->like('c.razon_social', $search)
            ->orLike('c.marca', $search)
            ->orLike('cg.nombre', $search)
            ->orLike('u.nombre', $search)
            ->groupEnd();
    }
}

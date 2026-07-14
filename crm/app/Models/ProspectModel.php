<?php

namespace App\Models;

use CodeIgniter\Model;

final class ProspectModel extends Model
{
    protected $table = 'cpotencial';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'razon_social', 'marca', 'rfc', 'sector_id', 'ejecutivo_id', 'cliente_id',
        'u_crea', 'u_modifica', 'f_creacion', 'f_modificacion', 'deleted',
        '_countries_id', 'estado', 'ciudad', 'cp', 'direccion',
    ];
    protected $useTimestamps = false;

    /**
     * @return list<array<string, mixed>>
     */
    public function activeRows(array $identity, string $scope, ?string $search = null): array
    {
        $builder = $this->baseBuilder()
            ->where('cp.deleted', 0)
            ->where('cp.cliente_id', null)
            ->orderBy('cp.id', 'DESC');

        $this->applyScope($builder, $identity, $scope);
        $this->applySearch($builder, $search);

        return $builder->get()->getResultArray();
    }

    public function activeById(int $id, array $identity, string $scope): ?array
    {
        $builder = $this->baseBuilder()
            ->where('cp.id', $id)
            ->where('cp.deleted', 0)
            ->where('cp.cliente_id', null);

        $this->applyScope($builder, $identity, $scope);

        return $builder->get()->getRowArray();
    }

    public function duplicateExists(string $businessName, string $brand, ?int $exceptId = null): bool
    {
        $builder = $this->builder()
            ->where('deleted', 0)
            ->where('cliente_id', null)
            ->where('razon_social', $businessName)
            ->where('marca', $brand);

        if ($exceptId !== null) {
            $builder->where('id !=', $exceptId);
        }

        return $builder->countAllResults() > 0;
    }

    private function baseBuilder(): object
    {
        return $this->db->table($this->table . ' cp')
            ->select('cp.*, s.nombre AS sector, u.nombre AS ejecutivo, uuc.ucomercial_id')
            ->join('sector s', 'cp.sector_id = s.id', 'left')
            ->join('usuario u', 'cp.ejecutivo_id = u.id', 'left')
            ->join('usuario_ucomercial uuc', 'uuc.usuario_id = u.id AND uuc.deleted = 0', 'left')
            ->groupBy('cp.id, cp.razon_social, cp.marca, cp.rfc, cp.sector_id, cp.ejecutivo_id, cp.cliente_id, cp.u_crea, cp.u_modifica, cp.f_creacion, cp.f_modificacion, cp.deleted, cp._countries_id, cp.estado, cp.ciudad, cp.cp, cp.direccion, s.nombre, u.nombre, uuc.ucomercial_id');
    }

    private function applyScope(object $builder, array $identity, string $scope): void
    {
        if ($scope === 'owner') {
            $builder->where('cp.ejecutivo_id', (int) ($identity['user_id'] ?? 0));
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
            ->like('cp.razon_social', $search)
            ->orLike('cp.marca', $search)
            ->orLike('cp.rfc', $search)
            ->orLike('u.nombre', $search)
            ->groupEnd();
    }
}

<?php

namespace App\Models;

use CodeIgniter\Model;

final class FollowUpModel extends Model
{
    protected $table = 'seguimiento';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['fecha','hora','actividad_id','descripcion','adjunto','estado_id','cliente_id','ejecutivo_id','tipo_id','monto','u_crea','u_modifica','f_creacion','f_modificacion','deleted'];
    protected $useTimestamps = false;

    /** @return list<array<string,mixed>> */
    public function activeRows(array $identity, string $scope, ?string $search = null): array
    {
        $builder = $this->baseBuilder()->where('s.deleted', 0)->groupStart()->where('cl.id IS NOT', null)->orWhere('cp.id IS NOT', null)->groupEnd()->orderBy('s.fecha', 'DESC')->orderBy('s.hora', 'DESC');
        $this->applyScope($builder, $identity, $scope);
        $this->applySearch($builder, $search);
        return $builder->get()->getResultArray();
    }

    public function activeById(int $id, array $identity, string $scope): ?array
    {
        $builder = $this->baseBuilder()->where('s.id', $id)->where('s.deleted', 0)->groupStart()->where('cl.id IS NOT', null)->orWhere('cp.id IS NOT', null)->groupEnd();
        $this->applyScope($builder, $identity, $scope);
        return $builder->get()->getRowArray();
    }

    /** @return list<array<string,mixed>> */
    public function activeByParent(string $parentType, int $parentId, array $identity, string $scope, ?string $search = null): array
    {
        $builder = $this->baseBuilder()->where('s.deleted', 0)->orderBy('s.fecha', 'DESC')->orderBy('s.hora', 'DESC');
        if ($parentType === 'cliente') {
            $builder->where('s.tipo_id', 1)->where('s.cliente_id', $parentId);
        } elseif ($parentType === 'cpotencial') {
            $builder->where('s.tipo_id', 2)->where('s.cliente_id', $parentId);
        } else {
            $builder->where('1 =', 0, false);
        }
        $this->applyScope($builder, $identity, $scope);
        $this->applySearch($builder, $search);
        return $builder->get()->getResultArray();
    }

    public function parentIsAccessible(string $parentType, int $parentId, array $identity, string $scope): bool
    {
        $builder = $this->db->table($parentType === 'cliente' ? 'cliente p' : 'cpotencial p')->select('p.id')->join('usuario_ucomercial uuc', 'uuc.usuario_id = p.ejecutivo_id AND uuc.deleted = 0', 'left')->where('p.id', $parentId)->where('p.deleted', 0);
        if ($parentType === 'cpotencial') { $builder->where('p.cliente_id', null); } elseif ($parentType !== 'cliente') { return false; }
        if ($scope === 'owner') { $builder->where('p.ejecutivo_id', (int) ($identity['user_id'] ?? 0)); }
        elseif ($scope === 'team') { $builder->where('uuc.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0)); }
        elseif ($scope !== 'all') { return false; }
        return $builder->get()->getRowArray() !== null;
    }

    /** @return list<array{id:string,text:string}> */
    public function parentOptions(array $identity, string $scope, ?string $search = null): array
    {
        $items = [];
        foreach (['cliente' => 1, 'cpotencial' => 2] as $type => $legacyType) {
            $table = $type . ' p';
            $builder = $this->db->table($table)->select('p.id, p.razon_social, p.marca')->join('usuario_ucomercial uuc', 'uuc.usuario_id = p.ejecutivo_id AND uuc.deleted = 0', 'left')->where('p.deleted', 0)->orderBy('p.razon_social');
            if ($type === 'cpotencial') { $builder->where('p.cliente_id', null); }
            if ($search !== null && $search !== '') { $builder->groupStart()->like('p.razon_social', $search)->orLike('p.marca', $search)->groupEnd(); }
            if ($scope === 'owner') { $builder->where('p.ejecutivo_id', (int) ($identity['user_id'] ?? 0)); }
            elseif ($scope === 'team') { $builder->where('uuc.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0)); }
            elseif ($scope !== 'all') { continue; }
            foreach ($builder->get()->getResultArray() as $row) {
                $items[] = ['id' => (int) $row['id'] . '_' . $legacyType, 'text' => ($legacyType === 1 ? 'Cliente: ' : 'Prospecto: ') . $row['razon_social'] . ' - ' . $row['marca']];
            }
        }
        return $items;
    }

    private function baseBuilder(): object
    {
        return $this->db->table($this->table . ' s')
            ->select('s.*, a.nombre AS actividad, e.nombre AS estado, u.nombre AS ejecutivo, cl.razon_social AS cliente, cl.marca AS cliente_marca, cp.razon_social AS cpotencial, cp.marca AS cpotencial_marca, uc.nombre AS creador')
            ->join('actividad a', 's.actividad_id = a.id AND a.deleted = 0', 'inner')
            ->join('estado e', 's.estado_id = e.id AND e.deleted = 0', 'inner')
            ->join('usuario u', 's.ejecutivo_id = u.id AND u.deleted = 0', 'inner')
            ->join('usuario uc', 's.u_crea = uc.id', 'left')
            ->join('cliente cl', 's.tipo_id = 1 AND s.cliente_id = cl.id AND cl.deleted = 0', 'left')
            ->join('cpotencial cp', 's.tipo_id = 2 AND s.cliente_id = cp.id AND cp.deleted = 0 AND cp.cliente_id IS NULL', 'left')
            ->join('usuario_ucomercial uuc_fu', 'uuc_fu.usuario_id = s.ejecutivo_id AND uuc_fu.deleted = 0', 'left')
            ->join('usuario_ucomercial uuc_cl', 'uuc_cl.usuario_id = cl.ejecutivo_id AND uuc_cl.deleted = 0', 'left')
            ->join('usuario_ucomercial uuc_cp', 'uuc_cp.usuario_id = cp.ejecutivo_id AND uuc_cp.deleted = 0', 'left');
    }

    private function applyScope(object $builder, array $identity, string $scope): void
    {
        if ($scope === 'owner') { $builder->where('s.ejecutivo_id', (int) ($identity['user_id'] ?? 0)); return; }
        if ($scope === 'team') { $builder->where('uuc_fu.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0)); return; }
        if ($scope !== 'all') { $builder->where('1 =', 0, false); }
    }

    private function applySearch(object $builder, ?string $search): void
    {
        if ($search === null || $search === '') { return; }
        $builder->groupStart()->like('a.nombre', $search)->orLike('e.nombre', $search)->orLike('u.nombre', $search)->orLike('cl.razon_social', $search)->orLike('cp.razon_social', $search)->orLike('s.descripcion', $search)->groupEnd();
    }
}
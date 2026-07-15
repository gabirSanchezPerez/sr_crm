<?php

namespace App\Models;

use CodeIgniter\Model;

final class ProposalModel extends Model
{
    protected $table = 'propuesta';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'nombre', 'canal_id', 'monto', 'cliente_id', 'cpotencial_id', 'contacto_id',
        'estado_id', 'ejecutivo_id', 'descripcion', 'u_crea', 'u_modifica',
        'f_creacion', 'f_modificacion', 'deleted',
    ];
    protected $useTimestamps = false;

    /** @return list<array<string,mixed>> */
    public function activeRows(array $identity, string $scope, ?string $search = null): array
    {
        if (! $this->db->tableExists($this->table)) {
            return [];
        }
        $builder = $this->baseBuilder()->where('p.deleted', 0)->orderBy('p.id', 'DESC');
        $this->applyScope($builder, $identity, $scope);
        $this->applySearch($builder, $search);
        return $builder->get()->getResultArray();
    }

    public function activeById(int $id, array $identity, string $scope): ?array
    {
        if (! $this->db->tableExists($this->table)) {
            return null;
        }
        $builder = $this->baseBuilder()->where('p.id', $id)->where('p.deleted', 0);
        $this->applyScope($builder, $identity, $scope);
        return $builder->get()->getRowArray();
    }

    /** @return list<array<string,mixed>> */
    public function activeByParent(string $parentType, int $parentId, array $identity, string $scope, ?string $search = null): array
    {
        if (! $this->db->tableExists($this->table)) {
            return [];
        }
        $builder = $this->baseBuilder()->where('p.deleted', 0)->orderBy('p.id', 'DESC');
        if ($parentType === 'cliente') {
            $builder->where('p.cliente_id', $parentId)->where('p.cpotencial_id', null);
        } elseif ($parentType === 'cpotencial') {
            $builder->where('p.cpotencial_id', $parentId)->where('p.cliente_id', null);
        } else {
            $builder->where('1 =', 0, false);
        }
        $this->applyScope($builder, $identity, $scope);
        $this->applySearch($builder, $search);
        return $builder->get()->getResultArray();
    }

    public function parentIsAccessible(string $parentType, int $parentId, array $identity, string $scope): bool
    {
        if (! $this->db->tableExists($parentType)) {
            return false;
        }
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

    /** @return list<array{id:string,text:string}> */
    public function parentOptions(array $identity, string $scope, ?string $search = null): array
    {
        $items = [];
        foreach (['cliente' => 1, 'cpotencial' => 2] as $type => $legacyType) {
            if (! $this->db->tableExists($type)) {
                continue;
            }
            $builder = $this->db->table($type . ' p')
                ->select('p.id, p.razon_social, p.marca')
                ->join('usuario_ucomercial uuc', 'uuc.usuario_id = p.ejecutivo_id AND uuc.deleted = 0', 'left')
                ->where('p.deleted', 0)
                ->orderBy('p.razon_social');
            if ($type === 'cpotencial') {
                $builder->where('p.cliente_id', null);
            }
            if ($search !== null && $search !== '') {
                $builder->groupStart()->like('p.razon_social', $search)->orLike('p.marca', $search)->groupEnd();
            }
            if ($scope === 'owner') {
                $builder->where('p.ejecutivo_id', (int) ($identity['user_id'] ?? 0));
            } elseif ($scope === 'team') {
                $builder->where('uuc.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0));
            } elseif ($scope !== 'all') {
                continue;
            }
            foreach ($builder->get()->getResultArray() as $row) {
                $items[] = ['id' => (int) $row['id'] . '_' . $legacyType, 'text' => ($legacyType === 1 ? 'Cliente: ' : 'Prospecto: ') . $row['razon_social'] . ' - ' . $row['marca']];
            }
        }
        return $items;
    }

    /** @return array<int,string> */
    public function contactOptions(string $parentType, int $parentId): array
    {
        if (! $this->db->tableExists('contacto')) {
            return [];
        }
        $builder = $this->db->table('contacto co')->select('co.id, co.nombre')->where('co.deleted', 0)->orderBy('co.nombre');
        if ($parentType === 'cliente') {
            $builder->where('co.cliente_id', $parentId)->where('co.cpotencial_id', null);
        } elseif ($parentType === 'cpotencial') {
            $builder->where('co.cpotencial_id', $parentId)->where('co.cliente_id', null);
        } else {
            return [];
        }
        $options = [];
        foreach ($builder->get()->getResultArray() as $row) {
            $options[(int) $row['id']] = (string) $row['nombre'];
        }
        return $options;
    }

    public function contactBelongsToParent(int $contactId, string $parentType, int $parentId): bool
    {
        if (! $this->db->tableExists('contacto')) {
            return false;
        }
        $builder = $this->db->table('contacto')->where('id', $contactId)->where('deleted', 0);
        if ($parentType === 'cliente') {
            $builder->where('cliente_id', $parentId)->where('cpotencial_id', null);
        } elseif ($parentType === 'cpotencial') {
            $builder->where('cpotencial_id', $parentId)->where('cliente_id', null);
        } else {
            return false;
        }
        return $builder->countAllResults() > 0;
    }

    public function activityThreeIsProposalDelivery(): bool
    {
        if (! $this->db->tableExists('actividad')) {
            return false;
        }
        $row = $this->db->table('actividad')->select('nombre')->where('id', 3)->where('deleted', 0)->get()->getRowArray();
        return $row !== null && stripos($this->normalize((string) $row['nombre']), 'entrega de propuesta') !== false;
    }

    private function baseBuilder(): object
    {
        return $this->db->table($this->table . ' p')
            ->select('p.*, cg.nombre AS canal, e.nombre AS estado, co.nombre AS contacto, u.nombre AS ejecutivo, cl.razon_social AS cliente, cl.marca AS cliente_marca, cp.razon_social AS cpotencial, cp.marca AS cpotencial_marca')
            ->join('cgestion cg', 'p.canal_id = cg.id AND cg.deleted = 0', 'inner')
            ->join('estado e', 'p.estado_id = e.id AND e.deleted = 0', 'inner')
            ->join('contacto co', 'p.contacto_id = co.id AND co.deleted = 0', 'inner')
            ->join('usuario u', 'p.ejecutivo_id = u.id AND u.deleted = 0', 'inner')
            ->join('cliente cl', 'p.cliente_id = cl.id AND cl.deleted = 0', 'left')
            ->join('cpotencial cp', 'p.cpotencial_id = cp.id AND cp.deleted = 0 AND cp.cliente_id IS NULL', 'left')
            ->join('usuario_ucomercial uuc_prop', 'uuc_prop.usuario_id = p.ejecutivo_id AND uuc_prop.deleted = 0', 'left')
            ->join('usuario_ucomercial uuc_cl', 'uuc_cl.usuario_id = cl.ejecutivo_id AND uuc_cl.deleted = 0', 'left')
            ->join('usuario_ucomercial uuc_cp', 'uuc_cp.usuario_id = cp.ejecutivo_id AND uuc_cp.deleted = 0', 'left');
    }

    private function applyScope(object $builder, array $identity, string $scope): void
    {
        if ($scope === 'owner') {
            $builder->where('p.ejecutivo_id', (int) ($identity['user_id'] ?? 0));
            return;
        }
        if ($scope === 'team') {
            $builder->where('uuc_prop.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0));
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
            ->like('p.nombre', $search)
            ->orLike('cl.razon_social', $search)
            ->orLike('cp.razon_social', $search)
            ->orLike('co.nombre', $search)
            ->groupEnd();
    }

    private function normalize(string $value): string
    {
        $value = strtolower(iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value);
        return preg_replace('/\s+/', ' ', trim($value)) ?: '';
    }
}

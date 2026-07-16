<?php

namespace App\Models;

use CodeIgniter\Model;

final class SalesGoalModel extends Model
{
    protected $table = 'meta_venta';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['anio','mes','usuario_id','ucomercial_id','asignado_por','monto','u_crea','u_modifica','f_creacion','f_modificacion','deleted'];
    protected $useTimestamps = false;

    /** @return list<array<string,mixed>> */
    public function annualRows(int $year, array $identity): array
    {
        $profile = (int) ($identity['perfil_id'] ?? 0);
        $builder = $this->db->table('meta_venta m')->select('m.*, u.nombre AS usuario, u.perfil_id, uc.nombre AS unidad')
            ->join('usuario u', 'u.id=m.usuario_id AND u.deleted=0', 'inner')
            ->join('ucomercial uc', 'uc.id=m.ucomercial_id AND uc.deleted=0', 'inner')
            ->where('m.anio', $year)->where('m.deleted', 0)->orderBy('u.perfil_id')->orderBy('u.nombre')->orderBy('m.mes');
        if ($profile === 2) $builder->where('m.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0))->where('u.perfil_id', 3);
        elseif ($profile === 3) $builder->where('m.usuario_id', (int) ($identity['user_id'] ?? 0));
        elseif ($profile !== 1) $builder->where('1 =', 0, false);
        return $builder->get()->getResultArray();
    }

    public function logical(int $year, int $month, int $userId, int $unitId): ?array
    { return $this->where(['anio'=>$year,'mes'=>$month,'usuario_id'=>$userId,'ucomercial_id'=>$unitId,'deleted'=>0])->first(); }

    public function executiveTotal(int $year, int $month, int $unitId): int
    {
        $row = $this->db->table('meta_venta m')
            ->select('COALESCE(SUM(m.monto),0) total', false)
            ->join('usuario u', 'u.id=m.usuario_id AND u.deleted=0 AND u.perfil_id=3', 'inner')
            ->where(['m.anio'=>$year,'m.mes'=>$month,'m.ucomercial_id'=>$unitId,'m.deleted'=>0])
            ->get()->getRowArray();
        return (int) round(((float) ($row['total'] ?? 0)) * 100);
    }
}

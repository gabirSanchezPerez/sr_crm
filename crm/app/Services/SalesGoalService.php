<?php

namespace App\Services;

use App\Models\SalesGoalModel;
use InvalidArgumentException;
use RuntimeException;

final class SalesGoalService
{
    public function __construct(private readonly SalesGoalModel $model = new SalesGoalModel(), private readonly GoalEditPolicy $policy = new GoalEditPolicy()) {}

    /** @return array<string,mixed> */
    public function annual(int $year, array $identity): array
    {
        $rows = $this->model->annualRows($year, $identity);
        $people = [];
        $profile = (int)($identity['perfil_id'] ?? 0);
        $targets = db_connect()->table('usuario u')->select('u.id,u.nombre,u.perfil_id,x.ucomercial_id,uc.nombre unidad')->join('usuario_ucomercial x', 'x.usuario_id=u.id AND x.deleted=0', 'inner')->join('ucomercial uc', 'uc.id=x.ucomercial_id AND uc.deleted=0', 'inner')->where('u.deleted', 0);
        if ($profile === 1) $targets->where('u.perfil_id', 2);
        elseif ($profile === 2) $targets->where('u.perfil_id', 3)->where('x.ucomercial_id', (int)($identity['ucomercial_id'] ?? 0));
        elseif ($profile === 3) $targets->where('u.id', (int)($identity['user_id'] ?? 0));
        else $targets->where('1 =', 0, false);
        foreach ($targets->orderBy('u.nombre')->get()->getResultArray() as $row) {
            $id = (int)$row['id'];
            $key = $id . ':' . $row['ucomercial_id'];
            $people[$key] = ['id' => $id, 'nombre' => $row['nombre'], 'perfil_id' => (int)$row['perfil_id'], 'unidad' => $row['unidad'], 'ucomercial_id' => (int)$row['ucomercial_id'], 'months' => array_fill(1, 12, 0.0), 'total' => 0.0];
        }
        foreach ($rows as $row) {
            $id = (int)$row['usuario_id'];
            $key = $id . ':' . $row['ucomercial_id'];
            $people[$key] ??= ['id' => $id, 'nombre' => $row['usuario'], 'perfil_id' => (int)$row['perfil_id'], 'unidad' => $row['unidad'], 'ucomercial_id' => (int)$row['ucomercial_id'], 'months' => array_fill(1, 12, 0.0), 'total' => 0.0];
            $people[$key]['months'][(int)$row['mes']] = (float)$row['monto'];
            $people[$key]['total'] += (float)$row['monto'];
        }
        $distribution = [];
        if ($profile === 2) {
            for ($m = 1; $m <= 12; $m++) {
                $limit = $this->model->logical($year, $m, (int)$identity['user_id'], (int)$identity['ucomercial_id']);
                $assigned = $this->model->executiveTotal($year, $m, (int)$identity['ucomercial_id']);
                $limitCents = (int)round((float)($limit['monto'] ?? 0) * 100);
                $distribution[$m] = ['limit' => $limitCents / 100, 'assigned' => $assigned / 100, 'remaining' => max(0, ($limitCents - $assigned) / 100)];
            }
        }
        return ['year' => $year, 'people' => array_values($people), 'distribution' => $distribution, 'editable' => array_combine(range(1, 12), array_map(fn($m) => $this->policy->editable($year, $m), range(1, 12)))];
    }

    public function save(array $input, array $identity): void
    {
        $profile = (int)($identity['perfil_id'] ?? 0);
        $actor = (int)($identity['user_id'] ?? 0);
        $year = (int)($input['anio'] ?? 0);
        $month = (int)($input['mes'] ?? 0);
        $userId = (int)($input['usuario_id'] ?? 0);
        $unitId = (int)($input['ucomercial_id'] ?? 0);
        $cents = $this->cents((string)($input['monto'] ?? ''));

        if (!in_array($profile, [1, 2], true)) throw new InvalidArgumentException('No tienes permiso para modificar metas.');
        if (!$this->policy->editable($year, $month)) throw new InvalidArgumentException('El mes seleccionado esta cerrado.');

        $target = db_connect()->table('usuario u')->select('u.perfil_id')->join('usuario_ucomercial x', 'x.usuario_id=u.id AND x.deleted=0', 'inner')->where(['u.id' => $userId, 'u.deleted' => 0, 'x.ucomercial_id' => $unitId])->get()->getRowArray();
        $expected = $profile === 1 ? 2 : 3;
        if ($target === null || (int)$target['perfil_id'] !== $expected || ($profile === 2 && $unitId !== (int)($identity['ucomercial_id'] ?? 0))) throw new InvalidArgumentException('El usuario no pertenece a la jerarquia permitida.');
        $db = db_connect();
        $db->transStart();
        $existing = $this->model->logical($year, $month, $userId, $unitId);
        if ($profile === 2) {
            $manager = $db->table('meta_venta m')
                ->select('m.monto')
                ->join('usuario u', 'u.id=m.usuario_id AND u.perfil_id=2 AND u.deleted=0', 'inner')
                ->where(['m.anio' => $year, 'm.mes' => $month, 'm.ucomercial_id' => $unitId, 'm.usuario_id' => $actor, 'm.deleted' => 0])
                ->get()->getRowArray();
            $limit = (int)round(((float)($manager['monto'] ?? 0)) * 100);
            $current = $this->model->executiveTotal($year, $month, $unitId) - (int)round(((float)($existing['monto'] ?? 0)) * 100);
            if ($current + $cents > $limit) {
                $db->transRollback();
                throw new InvalidArgumentException('La distribucion supera la meta del gerente.');
            }
        }
        $now = date('Y-m-d H:i:s');
        $data = ['anio' => $year, 'mes' => $month, 'usuario_id' => $userId, 'ucomercial_id' => $unitId, 'asignado_por' => $actor, 'monto' => number_format($cents / 100, 2, '.', ''), 'deleted' => 0];
        $ok = $existing ? $this->model->update($existing['id'], $data + ['u_modifica' => $actor, 'f_modificacion' => $now]) : $this->model->insert($data + ['u_crea' => $actor, 'f_creacion' => $now]);
        if ($ok === false) {
            $db->transRollback();
            throw new RuntimeException('No fue posible guardar la meta.');
        }
        $db->transComplete();
        if (!$db->transStatus()) throw new RuntimeException('No fue posible guardar la meta.');
    }

    /** @param array<string,mixed> $input */
    public function saveBatch(array $input, array $identity): void
    {
        $year = (int) ($input['anio'] ?? 0);
        foreach ((array) ($input['metas'] ?? []) as $userId => $units) {
            foreach ((array) $units as $unitId => $months) {
                foreach ((array) $months as $month => $amount) {
                    if ((string) $amount === '') {
                        continue;
                    }
                    if (! $this->policy->editable($year, (int) $month)) {
                        continue;
                    }
                    $this->save(['anio' => $year, 'mes' => (int)$month, 'usuario_id' => (int)$userId, 'ucomercial_id' => (int)$unitId, 'monto' => (string)$amount], $identity);
                }
            }
        }
    }

    private function cents(string $amount): int
    {
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) throw new InvalidArgumentException('Monto invalido.');
        return (int)round(((float)$amount) * 100);
    }
}

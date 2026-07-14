<?php

namespace App\Models;

use CodeIgniter\Model;

final class UserModel extends Model
{
    protected $table = 'usuario';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = [
        'nombre', 'usuario', 'correo', 'contrasenia', 'perfil_id', 'cgestion_id',
        'deleted', 'u_crea', 'f_creacion', 'u_modifica', 'f_modificacion',
        'conection_end',
    ];
    protected $useTimestamps = false;

    public function findActiveByIdentity(string $identity): ?array
    {
        return $this->groupStart()
            ->where('correo', $identity)
            ->orWhere('usuario', $identity)
            ->groupEnd()
            ->where('deleted', 0)
            ->first();
    }

    public function verifyPassword(array $user, string $password): bool
    {
        $hash = (string) ($user['contrasenia'] ?? '');
        if ($hash === '' || $password === '') {
            return false;
        }

        if (password_verify($password, $hash)) {
            if (password_needs_rehash($hash, PASSWORD_DEFAULT)) {
                $this->update($user['id'], ['contrasenia' => password_hash($password, PASSWORD_DEFAULT)]);
            }
            return true;
        }

        $salt = substr($hash, 0, 10);
        $legacy = $salt . substr(sha1($salt . $password), 0, -10);
        if (! hash_equals($hash, $legacy)) {
            return false;
        }

        $this->update($user['id'], ['contrasenia' => password_hash($password, PASSWORD_DEFAULT)]);
        return true;
    }

    public function findActiveById(int $id): ?array
    {
        return $this->where('id', $id)->where('deleted', 0)->first();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function administrationRows(array $identity, string $scope, ?string $search = null): array
    {
        $builder = $this->db->table($this->table . ' u')
            ->select('u.id, u.nombre, u.usuario, u.correo, u.perfil_id, u.cgestion_id, u.deleted, p.nombre AS perfil, cg.nombre AS cgestion')
            ->join('perfil p', 'u.perfil_id = p.id', 'left')
            ->join('cgestion cg', 'u.cgestion_id = cg.id', 'left')
            ->where('u.deleted', 0)
            ->orderBy('u.nombre', 'ASC');

        if ($scope === 'owner') {
            $builder->where('u.id', (int) ($identity['user_id'] ?? 0));
        } elseif ($scope === 'team') {
            $builder->join('usuario_ucomercial uuc', 'uuc.usuario_id = u.id', 'inner')
                ->where('u.perfil_id >', 1)
                ->where('uuc.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0))
                ->where('uuc.deleted', 0)
                ->groupBy('u.id, u.nombre, u.usuario, u.correo, u.perfil_id, u.cgestion_id, u.deleted, p.nombre, cg.nombre');
        } elseif ($scope !== 'all') {
            $builder->where('1 =', 0, false);
        }

        if ($search !== null && $search !== '') {
            $builder->groupStart()
                ->like('u.nombre', $search)
                ->orLike('u.usuario', $search)
                ->orLike('u.correo', $search)
                ->orLike('p.nombre', $search)
                ->orLike('cg.nombre', $search)
                ->groupEnd();
        }

        return $builder->get()->getResultArray();
    }

    /**
     * @return list<int>
     */
    public function commercialUnitIds(int $userId): array
    {
        $rows = $this->db->table('usuario_ucomercial')
            ->select('ucomercial_id')
            ->where('usuario_id', $userId)
            ->where('deleted', 0)
            ->get()
            ->getResultArray();

        return array_map(static fn (array $row): int => (int) $row['ucomercial_id'], $rows);
    }
}

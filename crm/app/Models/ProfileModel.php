<?php

namespace App\Models;

use CodeIgniter\Model;

final class ProfileModel extends Model
{
    protected $table = 'perfil';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $allowedFields = ['nombre', 'deleted', 'u_crea', 'f_creacion', 'u_modifica', 'f_modificacion'];
    protected $useTimestamps = false;

    /**
     * @return array<int, string>
     */
    public function options(): array
    {
        $options = [];
        foreach ($this->where('deleted', 0)->orderBy('nombre', 'ASC')->findAll() as $row) {
            $options[(int) $row['id']] = (string) $row['nombre'];
        }
        return $options;
    }
}

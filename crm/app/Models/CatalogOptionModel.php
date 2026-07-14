<?php

namespace App\Models;

use CodeIgniter\Model;

final class CatalogOptionModel extends Model
{
    protected $returnType = 'array';

    /**
     * @return array<int, string>
     */
    public function activeOptions(string $table): array
    {
        $rows = $this->db->table($table)
            ->select('id, nombre')
            ->where('deleted', 0)
            ->orderBy('nombre', 'ASC')
            ->get()
            ->getResultArray();

        $options = [];
        foreach ($rows as $row) {
            $options[(int) $row['id']] = (string) $row['nombre'];
        }

        return $options;
    }
}

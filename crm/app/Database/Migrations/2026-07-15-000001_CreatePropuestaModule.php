<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreatePropuestaModule extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 245],
            'canal_id' => ['type' => 'INT', 'constraint' => 11],
            'monto' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'cliente_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'cpotencial_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'contacto_id' => ['type' => 'INT', 'constraint' => 11],
            'estado_id' => ['type' => 'INT', 'constraint' => 11],
            'ejecutivo_id' => ['type' => 'INT', 'constraint' => 11],
            'descripcion' => ['type' => 'TEXT', 'null' => true],
            'u_crea' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'u_modifica' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'f_creacion' => ['type' => 'DATETIME', 'null' => true],
            'f_modificacion' => ['type' => 'DATETIME', 'null' => true],
            'deleted' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['cliente_id', 'deleted']);
        $this->forge->addKey(['cpotencial_id', 'deleted']);
        $this->forge->addKey(['ejecutivo_id', 'deleted']);
        $this->forge->createTable('propuesta', true);

        if (! $this->db->fieldExists('propuesta_id', 'documento')) {
            $this->forge->addColumn('documento', [
                'propuesta_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'after' => 'cpotencial_id'],
            ]);
            $this->forge->addKey(['propuesta_id', 'deleted']);
        }

        if (! $this->db->fieldExists('propuesta_id', 'seguimiento')) {
            $this->forge->addColumn('seguimiento', [
                'propuesta_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true, 'after' => 'tipo_id'],
            ]);
            $this->forge->addKey(['propuesta_id', 'deleted']);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('propuesta_id', 'seguimiento')) {
            $this->forge->dropColumn('seguimiento', 'propuesta_id');
        }
        if ($this->db->fieldExists('propuesta_id', 'documento')) {
            $this->forge->dropColumn('documento', 'propuesta_id');
        }
        $this->forge->dropTable('propuesta', true);
    }
}

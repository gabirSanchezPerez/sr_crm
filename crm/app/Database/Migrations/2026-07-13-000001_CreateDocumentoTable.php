<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateDocumentoTable extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'cliente_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'cpotencial_id' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'nombre' => ['type' => 'VARCHAR', 'constraint' => 245],
            'archivo_original' => ['type' => 'VARCHAR', 'constraint' => 245],
            'archivo_ruta' => ['type' => 'VARCHAR', 'constraint' => 500],
            'mime' => ['type' => 'VARCHAR', 'constraint' => 120, 'null' => true],
            'tamano' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'default' => 0],
            'u_crea' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'u_modifica' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'f_creacion' => ['type' => 'DATETIME', 'null' => true],
            'f_modificacion' => ['type' => 'DATETIME', 'null' => true],
            'deleted' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['cliente_id', 'deleted']);
        $this->forge->addKey(['cpotencial_id', 'deleted']);
        $this->forge->createTable('documento', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('documento', true);
    }
}
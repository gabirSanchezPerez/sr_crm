<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateSalesGoalsAndProposalPayments extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'anio' => ['type' => 'INT', 'constraint' => 4],
            'mes' => ['type' => 'TINYINT', 'constraint' => 2],
            'usuario_id' => ['type' => 'INT', 'constraint' => 11],
            'ucomercial_id' => ['type' => 'INT', 'constraint' => 11],
            'asignado_por' => ['type' => 'INT', 'constraint' => 11],
            'monto' => ['type' => 'DECIMAL', 'constraint' => '18,2', 'default' => 0],
            'u_crea' => ['type' => 'INT', 'constraint' => 11],
            'u_modifica' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'f_creacion' => ['type' => 'DATETIME'],
            'f_modificacion' => ['type' => 'DATETIME', 'null' => true],
            'deleted' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['anio', 'mes', 'usuario_id', 'ucomercial_id']);
        $this->forge->addKey(['anio', 'mes', 'ucomercial_id', 'deleted']);
        $this->forge->addKey(['usuario_id', 'anio', 'deleted']);
        $this->forge->createTable('meta_venta', true);

        $this->forge->addField([
            'id' => ['type' => 'INT', 'constraint' => 11, 'unsigned' => true, 'auto_increment' => true],
            'propuesta_id' => ['type' => 'INT', 'constraint' => 11],
            'fecha_pago' => ['type' => 'DATE'],
            'monto' => ['type' => 'DECIMAL', 'constraint' => '18,2'],
            'secuencia' => ['type' => 'INT', 'constraint' => 11],
            'u_crea' => ['type' => 'INT', 'constraint' => 11],
            'u_modifica' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
            'f_creacion' => ['type' => 'DATETIME'],
            'f_modificacion' => ['type' => 'DATETIME', 'null' => true],
            'deleted' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['propuesta_id', 'secuencia']);
        $this->forge->addKey(['fecha_pago', 'deleted']);
        $this->forge->addKey(['propuesta_id', 'deleted']);
        $this->forge->createTable('propuesta_pago', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('propuesta_pago', true);
        $this->forge->dropTable('meta_venta', true);
    }
}

<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

final class DatabasePreflight extends BaseCommand
{
    protected $group = 'CRM';
    protected $name = 'crm:db-check';
    protected $description = 'Validates the adopted SQL Server CRM schema and baseline marker.';

    public function run(array $params): void
    {
        $db = Database::connect();
        $requiredTables = [
            '_cities', '_code_post', '_countries', '_states', '_tracer',
            'actividad', 'cgestion', 'cliente', 'contacto', 'cpotencial',
            'estado', 'marca', 'perfil', 'sector', 'seguimiento',
            'ucomercial', 'usuario', 'usuario_ucomercial',
        ];

        $missing = array_values(array_filter(
            $requiredTables,
            static fn (string $table): bool => ! $db->tableExists($table),
        ));

        if ($missing !== []) {
            throw new \RuntimeException('Missing baseline tables: ' . implode(', ', $missing));
        }

        $criticalColumns = [
            'usuario' => ['contrasenia', 'perfil_id'],
            'cliente' => ['ejecutivo_id', 'deleted'],
            'cpotencial' => ['cliente_id', 'deleted'],
            'seguimiento' => ['tipo_id', 'estado_id', 'deleted'],
        ];

        foreach ($criticalColumns as $table => $columns) {
            $actual = $db->getFieldNames($table);
            $absent = array_diff($columns, $actual);
            if ($absent !== []) {
                throw new \RuntimeException("Missing critical columns in {$table}: " . implode(', ', $absent));
            }
        }

        if (! $db->tableExists('crm_schema_baseline')) {
            throw new \RuntimeException('Baseline marker is absent; run docs/migration/baseline-adoption.sql.');
        }

        $marker = $db->table('crm_schema_baseline')
            ->where('baseline', 'crm-2026-07-10-migracion')
            ->countAllResults();

        if ($marker !== 1) {
            throw new \RuntimeException('Expected CRM baseline marker was not found exactly once.');
        }

        CLI::write('CRM database preflight passed.', 'green');
    }
}

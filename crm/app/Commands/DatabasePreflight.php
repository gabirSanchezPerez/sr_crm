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
            'ucomercial', 'usuario', 'usuario_ucomercial', 'propuesta',
            'meta_venta', 'propuesta_pago',
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
            'meta_venta' => ['anio', 'mes', 'usuario_id', 'ucomercial_id', 'monto', 'deleted'],
            'propuesta_pago' => ['propuesta_id', 'fecha_pago', 'monto', 'deleted'],
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

        foreach ([1, 2, 3] as $profileId) {
            if ($db->table('perfil')->where('id', $profileId)->where('deleted', 0)->countAllResults() !== 1) {
                throw new \RuntimeException("Required active profile {$profileId} was not found exactly once.");
            }
        }
        if ($db->table('usuario_ucomercial')->where('deleted', 0)->countAllResults() < 1) {
            throw new \RuntimeException('No active commercial-unit relationships were found.');
        }
        $sale = $db->table('estado')->select('nombre')->where('id', 4)->where('deleted', 0)->get()->getRowArray();
        if ($sale === null || mb_strtolower(trim((string) $sale['nombre'])) !== 'venta') {
            throw new \RuntimeException('Active estado.id = 4 must represent Venta.');
        }

        CLI::write('CRM database preflight passed.', 'green');
    }
}

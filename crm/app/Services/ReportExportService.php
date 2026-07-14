<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

final class ReportExportService
{
    /** @return array{path:string,filename:string,rows:array<string,list<array<string,mixed>>>} */
    public function exportFollowUps(array $identity, array $filters): array
    {
        $rows = $this->reportRows($identity, $filters, false);
        return $this->writeWorkbook('reporte-seguimiento', $rows);
    }

    /** @return array{path:string,filename:string,rows:array<string,list<array<string,mixed>>>} */
    public function exportWallet(array $identity, array $filters): array
    {
        $rows = $this->reportRows($identity, $filters, true);
        return $this->writeWorkbook('reporte-cartera', $rows);
    }

    /** @return array{rows:list<array<string,mixed>>,groups:array<string,list<array<string,mixed>>>,totals:array<string,int>} */
    public function followUpScreen(array $identity, array $filters): array
    {
        return $this->screenData($identity, $filters, false);
    }

    /** @return array{rows:list<array<string,mixed>>,groups:array<string,list<array<string,mixed>>>,totals:array<string,int>} */
    public function walletScreen(array $identity, array $filters): array
    {
        return $this->screenData($identity, $filters, true);
    }

    /** @return array{types:array<string,string>,units:array<int|string,string>,executives:array<int|string,string>} */
    public function filterOptions(array $identity): array
    {
        return [
            'types' => ['-1' => 'Todos', '1' => 'Anunciantes', '2' => 'Potenciales'],
            'units' => $this->unitOptions($identity),
            'executives' => $this->executiveOptions($identity),
        ];
    }

    /** @return array<string,list<array<string,mixed>>> */
    public function reportRows(array $identity, array $filters, bool $wallet): array
    {
        $type = (string) ($filters['t'] ?? '-1');
        $rows = ['a' => [], 'cp' => []];
        if ($type !== '2') {
            $rows['a'] = $this->accountRows('cliente', 1, 'Anunciante', $identity, $filters, $wallet);
        }
        if ($type !== '1') {
            $rows['cp'] = $this->accountRows('cpotencial', 2, 'Potencial', $identity, $filters, $wallet);
        }

        return $rows;
    }

    /** @return list<array<string,mixed>> */
    private function accountRows(string $table, int $typeId, string $label, array $identity, array $filters, bool $wallet): array
    {
        $db = db_connect();
        $builder = $db->table($table . ' a')
            ->select('uc.nombre AS unidad, u.nombre AS ejecutivo, a.marca, a.razon_social, s.nombre AS sector')
            ->select("'" . $label . "' AS tipo", false)
            ->select('a.f_creacion')
            ->join('sector s', 's.id = a.sector_id', 'left')
            ->join('usuario u', 'u.id = a.ejecutivo_id AND u.deleted = 0', 'inner')
            ->join('usuario_ucomercial uuc', 'uuc.usuario_id = u.id AND uuc.deleted = 0', 'left')
            ->join('ucomercial uc', 'uc.id = uuc.ucomercial_id AND uc.deleted = 0', 'left')
            ->where('a.deleted', 0)
            ->orderBy('a.marca', 'ASC')
            ->orderBy('a.razon_social', 'ASC');

        if ($table === 'cpotencial') {
            $builder->where('a.cliente_id', null);
        }

        if (! $wallet) {
            $followUpTable = $db->escapeIdentifiers($db->prefixTable('seguimiento'));
            $builder->select('(SELECT MAX(seg.f_creacion) FROM ' . $followUpTable . ' seg WHERE seg.tipo_id = ' . $typeId . ' AND seg.cliente_id = a.id AND seg.deleted = 0) AS f_seguimiento', false);
        }

        $unitId = $this->scopedUnitId($identity, $filters);
        if ($unitId > 0) {
            $builder->where('uuc.ucomercial_id', $unitId);
        }

        $executiveId = (int) ($filters['e'] ?? -1);
        if ($executiveId > 0) {
            $builder->where('a.ejecutivo_id', $executiveId);
        }

        return $builder->get()->getResultArray();
    }

    private function scopedUnitId(array $identity, array $filters): int
    {
        if ((int) ($identity['perfil_id'] ?? 0) === 2) {
            return (int) ($identity['ucomercial_id'] ?? 0);
        }

        return (int) ($filters['u'] ?? -1) > 0 ? (int) $filters['u'] : 0;
    }

    /** @return array{rows:list<array<string,mixed>>,groups:array<string,list<array<string,mixed>>>,totals:array<string,int>} */
    private function screenData(array $identity, array $filters, bool $wallet): array
    {
        $groups = $this->reportRows($identity, $filters, $wallet);
        $rows = [];
        foreach (['a', 'cp'] as $key) {
            foreach ($groups[$key] ?? [] as $row) {
                $rows[] = $row;
            }
        }

        return [
            'rows' => $rows,
            'groups' => $groups,
            'totals' => ['all' => count($rows), 'a' => count($groups['a'] ?? []), 'cp' => count($groups['cp'] ?? [])],
        ];
    }

    /** @return array<int|string,string> */
    private function unitOptions(array $identity): array
    {
        $builder = db_connect()->table('ucomercial')->select('id, nombre')->where('deleted', 0)->orderBy('nombre', 'ASC');
        $options = [];
        if ((int) ($identity['perfil_id'] ?? 0) === 2) {
            $builder->where('id', (int) ($identity['ucomercial_id'] ?? 0));
        } else {
            $options['-1'] = 'Todos';
        }
        foreach ($builder->get()->getResultArray() as $row) {
            $options[(int) $row['id']] = (string) $row['nombre'];
        }

        return $options;
    }

    /** @return array<int|string,string> */
    private function executiveOptions(array $identity): array
    {
        $builder = db_connect()->table('usuario u')
            ->select('u.id, u.nombre')
            ->join('usuario_ucomercial uuc', 'uuc.usuario_id = u.id AND uuc.deleted = 0', 'left')
            ->where('u.deleted', 0)
            ->orderBy('u.nombre', 'ASC')
            ->groupBy('u.id, u.nombre');

        if ((int) ($identity['perfil_id'] ?? 0) === 2) {
            $builder->where('uuc.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0));
        }

        $options = ['-1' => 'Todos'];
        foreach ($builder->get()->getResultArray() as $row) {
            $options[(int) $row['id']] = (string) $row['nombre'];
        }

        return $options;
    }

    /** @param array<string,list<array<string,mixed>>> $rows @return array{path:string,filename:string,rows:array<string,list<array<string,mixed>>>} */
    private function writeWorkbook(string $baseName, array $rows): array
    {
        $spreadsheet = new Spreadsheet();
        $sheetIndex = 0;
        $createdSheet = false;

        foreach ([['a', 'Anunciantes'], ['cp', 'Clientes Potenciales']] as [$key, $title]) {
            if (($rows[$key] ?? []) === []) {
                continue;
            }
            $sheet = $createdSheet ? $spreadsheet->createSheet($sheetIndex) : $spreadsheet->getActiveSheet();
            $createdSheet = true;
            $sheet->setTitle($title);
            $headers = array_keys($rows[$key][0]);
            foreach ($headers as $column => $header) {
                $sheet->setCellValue([$column + 1, 1], $header);
            }
            foreach ($rows[$key] as $rowIndex => $row) {
                foreach (array_values($row) as $column => $value) {
                    $sheet->setCellValue([$column + 1, $rowIndex + 2], $value);
                }
            }
            $sheetIndex++;
        }

        if (! $createdSheet) {
            $sheet = $spreadsheet->getActiveSheet();
            $sheet->setTitle('Sin resultados');
            $sheet->setCellValue('A1', 'Sin resultados');
        }

        $dir = rtrim(WRITEPATH . 'exports', DIRECTORY_SEPARATOR);
        if (! is_dir($dir) && ! mkdir($dir, 0775, true) && ! is_dir($dir)) {
            throw new RuntimeException('No fue posible preparar el directorio de exportacion.');
        }

        $filename = $this->safeFilename($baseName . '-' . date('Ymd-His') . '.xlsx');
        $path = $dir . DIRECTORY_SEPARATOR . $filename;
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return ['path' => $path, 'filename' => $filename, 'rows' => $rows];
    }

    private function safeFilename(string $filename): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'reporte.xlsx';
    }
}
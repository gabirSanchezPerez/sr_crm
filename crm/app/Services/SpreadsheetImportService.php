<?php

namespace App\Services;

use App\Models\CustomerModel;
use App\Models\ProspectModel;
use App\Models\UserModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\Files\UploadedFile;
use Config\Database;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;

final class SpreadsheetImportService
{
    private const ALLOWED_EXTENSIONS = ['xlsx', 'xls', 'csv'];
    private const MAX_SIZE_BYTES = 5242880;
    private const DEFAULT_PASSWORD = 'CRMventas';

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /** @return array<string, mixed> */
    public function importCustomers(UploadedFile $file, array $identity, int $actorId): array
    {
        return $this->importAccounts('cliente', $file, $identity, $actorId);
    }

    /** @return array<string, mixed> */
    public function importProspects(UploadedFile $file, array $identity, int $actorId): array
    {
        return $this->importAccounts('cpotencial', $file, $identity, $actorId);
    }

    /** @return array<string, mixed> */
    public function importUsers(UploadedFile $file, array $identity, int $actorId): array
    {
        $rows = $this->readRows($file, ['nombre', 'puesto', 'correo', 'perfil', 'perfil_id'], 'correo');
        $summary = $this->summary(count($rows), 'partial');
        $now = date('Y-m-d H:i:s');
        $defaultManagementId = (int) ($identity['cgestion_id'] ?? 1);
        $defaultUnitId = (int) ($identity['ucomercial_id'] ?? 0);

        foreach ($rows as $rowNumber => $row) {
            $errors = [];
            $name = trim((string) ($row['nombre'] ?? ''));
            $email = strtolower(trim((string) ($row['correo'] ?? '')));
            $profileId = (int) ($row['perfil_id'] ?? 0);
            if ($profileId <= 0 && trim((string) ($row['perfil'] ?? '')) !== '') {
                $profileId = $this->lookupId('perfil', (string) $row['perfil']);
            }

            if ($name === '') { $errors[] = 'nombre requerido'; }
            if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'correo invalido'; }
            if ($profileId <= 0 || ! $this->idExists('perfil', $profileId)) { $errors[] = 'perfil no encontrado'; }
            if ($defaultManagementId <= 0 || ! $this->idExists('cgestion', $defaultManagementId)) { $errors[] = 'canal de gestion no encontrado'; }

            if ($errors !== []) {
                $this->appendRow($summary, $rowNumber, 'failed', null, $errors);
                continue;
            }

            $existing = $this->db->table('usuario')
                ->select('id')
                ->groupStart()->where('correo', $email)->orWhere('nombre', $name)->groupEnd()
                ->where('deleted', 0)
                ->get()
                ->getRowArray();

            $username = explode('@', $email)[0] ?: $email;
            $data = [
                'nombre' => $name,
                'usuario' => $username,
                'correo' => $email,
                'perfil_id' => $profileId,
                'cgestion_id' => $defaultManagementId,
                'contrasenia' => password_hash(self::DEFAULT_PASSWORD, PASSWORD_DEFAULT),
            ];

            if ($existing !== null) {
                $this->db->table('usuario')->where('id', (int) $existing['id'])->update($data + ['u_modifica' => $actorId, 'f_modificacion' => $now]);
                $id = (int) $existing['id'];
                $status = 'updated';
            } else {
                $this->db->table('usuario')->insert($data + ['u_crea' => $actorId, 'f_creacion' => $now, 'deleted' => 0]);
                $id = (int) $this->db->insertID();
                $status = 'imported';
            }

            if ($defaultUnitId > 0 && $this->idExists('ucomercial', $defaultUnitId)) {
                $this->ensureUserUnit($id, $defaultUnitId, $actorId, $now);
            }

            $this->appendRow($summary, $rowNumber, $status, $id, []);
        }

        return $summary;
    }

    /** @return array<string, mixed> */
    private function importAccounts(string $module, UploadedFile $file, array $identity, int $actorId): array
    {
        $rows = $this->readRows($file, ['razon_social', 'marca', 'ucomercial', 'cgestion', 'ejecutivo', 'sector', 'rfc', 'estado', 'ciudad', 'direccion', 'cp'], 'ucomercial');
        $summary = $this->summary(count($rows), 'partial');
        $now = date('Y-m-d H:i:s');

        foreach ($rows as $rowNumber => $row) {
            $errors = [];
            $businessName = trim((string) ($row['razon_social'] ?? ''));
            $brand = trim((string) ($row['marca'] ?? ''));
            $sectorId = $this->lookupId('sector', (string) ($row['sector'] ?? ''));
            $executiveId = $this->lookupUser((string) ($row['ejecutivo'] ?? ''));
            $managementId = $executiveId > 0 ? $this->managementIdForUser($executiveId) : 0;

            if ($businessName === '') { $errors[] = 'razon_social requerida'; }
            if ($brand === '') { $errors[] = 'marca requerida'; }
            if ($sectorId <= 0) { $errors[] = 'sector no encontrado'; }
            if ($executiveId <= 0) { $errors[] = 'ejecutivo no encontrado'; }
            if ($module === 'cliente' && $managementId <= 0) { $errors[] = 'canal de gestion del ejecutivo no encontrado'; }

            if ($errors !== []) {
                $this->appendRow($summary, $rowNumber, 'failed', null, $errors);
                continue;
            }

            $duplicate = $module === 'cliente'
                ? (new CustomerModel())->duplicateExists($managementId, $businessName, $brand)
                : (new ProspectModel())->duplicateExists($businessName, $brand);

            if ($duplicate) {
                $this->appendRow($summary, $rowNumber, 'skipped', null, ['duplicado activo']);
                continue;
            }

            $data = [
                'razon_social' => $businessName,
                'marca' => $brand,
                'rfc' => strtoupper(trim((string) ($row['rfc'] ?? ''))),
                'sector_id' => $sectorId,
                'ejecutivo_id' => $executiveId,
                '_countries_id' => 42,
                'estado' => trim((string) ($row['estado'] ?? '')),
                'ciudad' => trim((string) ($row['ciudad'] ?? '')),
                'direccion' => trim((string) ($row['direccion'] ?? '')),
                'cp' => trim((string) ($row['cp'] ?? '')),
                'u_crea' => $actorId,
                'f_creacion' => $now,
                'deleted' => 0,
            ];

            if ($module === 'cliente') {
                $data['cgestion_id'] = $managementId;
                $this->db->table('cliente')->insert($data + ['cpotencial_id' => null]);
            } else {
                $this->db->table('cpotencial')->insert($data + ['cliente_id' => null]);
            }

            $this->appendRow($summary, $rowNumber, 'imported', (int) $this->db->insertID(), []);
        }

        return $summary;
    }

    /** @param list<string> $columns @return array<int, array<string, mixed>> */
    private function readRows(UploadedFile $file, array $columns, string $requiredColumn): array
    {
        $this->guardFile($file);
        try {
            $spreadsheet = IOFactory::load($file->getTempName());
        } catch (\Throwable $exception) {
            throw new InvalidArgumentException('No fue posible leer el spreadsheet.', 0, $exception);
        }

        $sheet = $spreadsheet->getSheet(0);
        $rows = [];
        foreach ($sheet->getRowIterator() as $rowNumber => $rowIterator) {
            if ($rowNumber === 1) { continue; }
            $row = [];
            foreach ($columns as $index => $column) {
                $cell = $sheet->getCell([$index + 1, $rowNumber]);
                $row[$column] = $cell->getCalculatedValue();
            }
            if (trim((string) ($row[$requiredColumn] ?? '')) === '') {
                continue;
            }
            $rows[$rowNumber] = $row;
        }
        $spreadsheet->disconnectWorksheets();

        return $rows;
    }

    private function guardFile(UploadedFile $file): void
    {
        if (! $file->isValid() && ! is_file($file->getTempName())) {
            throw new InvalidArgumentException('Archivo de importacion invalido.');
        }
        $extension = strtolower($file->getClientExtension() ?: pathinfo($file->getClientName(), PATHINFO_EXTENSION));
        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw new InvalidArgumentException('Formato no soportado. Usa xlsx, xls o csv.');
        }
        if ($file->getSize() <= 0 || $file->getSize() > self::MAX_SIZE_BYTES) {
            throw new InvalidArgumentException('El archivo excede el tamano permitido de 5 MB.');
        }
    }

    private function lookupId(string $table, string $name): int
    {
        $name = trim($name);
        if ($name === '') { return 0; }
        if (ctype_digit($name) && $this->idExists($table, (int) $name)) { return (int) $name; }
        $row = $this->db->table($table)->select('id')->where('deleted', 0)->where('nombre', $name)->get()->getRowArray();
        if ($row === null) {
            $row = $this->db->table($table)->select('id')->where('deleted', 0)->like('nombre', $name)->get()->getRowArray();
        }
        return $row === null ? 0 : (int) $row['id'];
    }

    private function lookupUser(string $value): int
    {
        $value = trim($value);
        if ($value === '') { return 0; }
        if (ctype_digit($value) && (new UserModel())->findActiveById((int) $value) !== null) { return (int) $value; }
        $row = $this->db->table('usuario')->select('id')
            ->where('deleted', 0)
            ->groupStart()->where('nombre', $value)->orWhere('usuario', $value)->orWhere('correo', $value)->groupEnd()
            ->get()->getRowArray();
        if ($row === null) {
            $row = $this->db->table('usuario')->select('id')->where('deleted', 0)->like('nombre', $value)->get()->getRowArray();
        }
        return $row === null ? 0 : (int) $row['id'];
    }

    private function managementIdForUser(int $userId): int
    {
        $user = (new UserModel())->findActiveById($userId);
        return $user === null ? 0 : (int) ($user['cgestion_id'] ?? 0);
    }

    private function idExists(string $table, int $id): bool
    {
        if ($id <= 0) { return false; }
        return $this->db->table($table)->where('id', $id)->where('deleted', 0)->countAllResults() > 0;
    }

    private function ensureUserUnit(int $userId, int $unitId, int $actorId, string $now): void
    {
        $exists = $this->db->table('usuario_ucomercial')
            ->where('usuario_id', $userId)
            ->where('ucomercial_id', $unitId)
            ->where('deleted', 0)
            ->countAllResults() > 0;
        if ($exists) { return; }
        $this->db->table('usuario_ucomercial')->insert([
            'usuario_id' => $userId,
            'ucomercial_id' => $unitId,
            'u_crea' => $actorId,
            'u_modifica' => $actorId,
            'f_creacion' => $now,
            'f_modificacion' => $now,
            'deleted' => 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function summary(int $totalRows, string $policy): array
    {
        return ['exito' => true, 'policy' => $policy, 'totalRows' => $totalRows, 'imported' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'rows' => []];
    }

    /** @param array<string, mixed> $summary @param list<string> $errors */
    private function appendRow(array &$summary, int $rowNumber, string $status, ?int $id, array $errors): void
    {
        if (isset($summary[$status])) { $summary[$status]++; }
        if ($errors !== []) { $summary['exito'] = false; }
        $summary['rows'][] = ['row' => $rowNumber, 'status' => $status, 'id' => $id, 'errors' => $errors];
    }
}
<?php

use App\Services\SpreadsheetImportService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

final class SpreadsheetImportFeatureTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['cpotencial', 'cliente', 'usuario_ucomercial', 'usuario', 'sector', 'ucomercial', 'cgestion', 'perfil'] as $table) {
            $db->query('DROP TABLE IF EXISTS ' . $db->escapeIdentifiers($db->prefixTable($table)));
        }

        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('perfil')) . ' (id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0)');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cgestion')) . ' (id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0)');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('ucomercial')) . ' (id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0)');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('sector')) . ' (id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0)');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('usuario')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, usuario TEXT NOT NULL, correo TEXT NOT NULL,
            contrasenia TEXT NOT NULL, perfil_id INTEGER NOT NULL, cgestion_id INTEGER NOT NULL,
            deleted INTEGER DEFAULT 0, u_crea INTEGER NULL, f_creacion TEXT NULL, u_modifica INTEGER NULL,
            f_modificacion TEXT NULL, conection_end TEXT NULL
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('usuario_ucomercial')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, usuario_id INTEGER NOT NULL, ucomercial_id INTEGER NOT NULL,
            u_crea INTEGER NULL, f_creacion TEXT NULL, u_modifica INTEGER NULL, f_modificacion TEXT NULL,
            deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cliente')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            razon_social TEXT NOT NULL,
            marca TEXT NOT NULL,
            rfc TEXT NULL,
            sector_id INTEGER NULL,
            cpotencial_id INTEGER NULL,
            cgestion_id INTEGER NOT NULL,
            ejecutivo_id INTEGER NOT NULL,
            u_crea INTEGER NULL,
            u_modifica INTEGER NULL,
            f_creacion TEXT NULL,
            f_modificacion TEXT NULL,
            deleted INTEGER DEFAULT 0,
            _countries_id INTEGER DEFAULT 42,
            estado TEXT NULL,
            ciudad TEXT NULL,
            cp TEXT NULL,
            direccion TEXT NULL
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cpotencial')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            razon_social TEXT NOT NULL,
            marca TEXT NOT NULL,
            rfc TEXT NULL,
            sector_id INTEGER NULL,
            ejecutivo_id INTEGER NOT NULL,
            cliente_id INTEGER NULL,
            u_crea INTEGER NULL,
            u_modifica INTEGER NULL,
            f_creacion TEXT NULL,
            f_modificacion TEXT NULL,
            deleted INTEGER DEFAULT 0,
            _countries_id INTEGER DEFAULT 42,
            estado TEXT NULL,
            ciudad TEXT NULL,
            cp TEXT NULL,
            direccion TEXT NULL
        )');

        foreach ([1, 2, 3, 4, 5, 6] as $profileId) {
            $db->table('perfil')->insert(['id' => $profileId, 'nombre' => 'Perfil ' . $profileId, 'deleted' => 0]);
        }
        $db->table('cgestion')->insert(['id' => 1, 'nombre' => 'Gestion Norte', 'deleted' => 0]);
        $db->table('ucomercial')->insert(['id' => 10, 'nombre' => 'Unidad Norte', 'deleted' => 0]);
        $db->table('sector')->insertBatch([
            ['id' => 1, 'nombre' => 'Retail', 'deleted' => 0],
            ['id' => 2, 'nombre' => 'Servicios', 'deleted' => 0],
        ]);
        $this->insertUser(1, 'Administrador', 'admin@example.com', 1, 1);
        $this->insertUser(3, 'Ejecutivo Norte', 'ejecutivo@example.com', 3, 1);

        $db->table('cliente')->insert([
            'id' => 20,
            'razon_social' => 'Cliente Duplicado',
            'marca' => 'Marca Duplicada',
            'rfc' => 'DUP010101AAA',
            'sector_id' => 1,
            'cgestion_id' => 1,
            'ejecutivo_id' => 3,
            'deleted' => 0,
            '_countries_id' => 42,
        ]);
    }

    public function testCustomerImportUsesPartialPolicyForValidDuplicateAndInvalidRows(): void
    {
        $file = $this->spreadsheetFile('clientes.xlsx', [
            ['Razon social', 'Marca', 'UComercial', 'CGestion', 'Ejecutivo', 'Sector', 'RFC', 'Estado', 'Ciudad', 'Direccion', 'CP'],
            ['Cliente Duplicado', 'Marca Duplicada', 'Unidad Norte', 'Gestion Norte', 'Ejecutivo Norte', 'Retail', 'DUP010101AAA', 'CDMX', 'CDMX', 'Calle 1', '01000'],
            ['Cliente Nuevo', 'Marca Nueva', 'Unidad Norte', 'Gestion Norte', 'Ejecutivo Norte', 'Servicios', 'NEW010101AAA', 'CDMX', 'CDMX', 'Calle 2', '02000'],
            ['Cliente Sin Sector', 'Marca Mala', 'Unidad Norte', 'Gestion Norte', 'Ejecutivo Norte', 'No Existe', 'BAD010101AAA', 'CDMX', 'CDMX', 'Calle 3', '03000'],
        ]);

        $summary = (new SpreadsheetImportService())->importCustomers($file, $this->identity(), 1);

        $this->assertFalse($summary['exito']);
        $this->assertSame('partial', $summary['policy']);
        $this->assertSame(3, $summary['totalRows']);
        $this->assertSame(1, $summary['imported']);
        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame('skipped', $summary['rows'][0]['status']);
        $this->assertSame('imported', $summary['rows'][1]['status']);
        $this->assertSame('failed', $summary['rows'][2]['status']);
        $this->assertSame(1, (int) db_connect()->table('cliente')->where('razon_social', 'Cliente Nuevo')->countAllResults());
        $this->assertSame(0, (int) db_connect()->table('cliente')->where('razon_social', 'Cliente Sin Sector')->countAllResults());
    }

    public function testImportRejectsUnsupportedFileExtensionBeforeReadingRows(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'import-test-');
        file_put_contents($path, '<?php echo "bad";');
        $file = new UploadedFile($path, 'shell.php', 'application/x-php', filesize($path), UPLOAD_ERR_OK);

        try {
            (new SpreadsheetImportService())->importProspects($file, $this->identity(), 1);
            $this->fail('Expected unsupported extension to be rejected.');
        } catch (InvalidArgumentException $exception) {
            $this->assertSame('Formato no soportado. Usa xlsx, xls o csv.', $exception->getMessage());
        }
    }

    public function testUserImportCreatesAndUpdatesRowsWithSummary(): void
    {
        $file = $this->spreadsheetFile('usuarios.xlsx', [
            ['Nombre', 'Puesto', 'Correo', 'Perfil', 'Perfil Id'],
            ['Ejecutivo Norte', 'Ventas', 'ejecutivo.nuevo@example.com', 'Perfil 3', 3],
            ['Nuevo Usuario', 'Ventas', 'nuevo@example.com', 'Perfil 3', 3],
            ['Usuario Sin Perfil', 'Ventas', 'sinperfil@example.com', 'No Existe', ''],
        ]);

        $summary = (new SpreadsheetImportService())->importUsers($file, $this->identity(), 1);

        $this->assertFalse($summary['exito']);
        $this->assertSame(1, $summary['imported']);
        $this->assertSame(1, $summary['updated']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame('updated', $summary['rows'][0]['status']);
        $this->assertSame('imported', $summary['rows'][1]['status']);
        $this->assertSame('ejecutivo.nuevo@example.com', db_connect()->table('usuario')->select('correo')->where('id', 3)->get()->getRowArray()['correo']);
        $this->assertSame(1, (int) db_connect()->table('usuario')->where('correo', 'nuevo@example.com')->countAllResults());
    }

    private function insertUser(int $id, string $name, string $email, int $profileId, int $managementId): void
    {
        db_connect()->table('usuario')->insert([
            'id' => $id,
            'nombre' => $name,
            'usuario' => explode('@', $email)[0],
            'correo' => $email,
            'contrasenia' => password_hash('secret123', PASSWORD_DEFAULT),
            'perfil_id' => $profileId,
            'cgestion_id' => $managementId,
            'deleted' => 0,
        ]);
        db_connect()->table('usuario_ucomercial')->insert(['usuario_id' => $id, 'ucomercial_id' => 10, 'deleted' => 0]);
    }

    /** @param list<list<mixed>> $rows */
    private function spreadsheetFile(string $name, array $rows): UploadedFile
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        foreach ($rows as $rowIndex => $row) {
            foreach ($row as $columnIndex => $value) {
                $sheet->setCellValue([$columnIndex + 1, $rowIndex + 1], $value);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'import-test-');
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', filesize($path), UPLOAD_ERR_OK);
    }

    /** @return array<string, mixed> */
    private function identity(): array
    {
        return ['user_id' => 1, 'perfil_id' => 1, 'cgestion_id' => 1, 'ucomercial_id' => 10, 'ucomercial_ids' => [10]];
    }
}
<?php

use App\Services\AuthorizationService;
use App\Services\ReportExportService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use PhpOffice\PhpSpreadsheet\IOFactory;

final class ReportExportFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['seguimiento', 'cpotencial', 'cliente', 'usuario_ucomercial', 'usuario', 'sector', 'ucomercial', 'cgestion', 'perfil'] as $table) {
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
            deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cliente')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, razon_social TEXT NOT NULL, marca TEXT NOT NULL,
            rfc TEXT NULL, sector_id INTEGER NULL, cgestion_id INTEGER NOT NULL, ejecutivo_id INTEGER NOT NULL,
            f_creacion TEXT NULL, deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cpotencial')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, razon_social TEXT NOT NULL, marca TEXT NOT NULL,
            rfc TEXT NULL, sector_id INTEGER NULL, ejecutivo_id INTEGER NOT NULL, cliente_id INTEGER NULL,
            f_creacion TEXT NULL, deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('seguimiento')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tipo_id INTEGER NOT NULL, cliente_id INTEGER NOT NULL,
            f_creacion TEXT NULL, deleted INTEGER DEFAULT 0
        )');

        foreach ([1, 2, 3, 4, 5, 6] as $profileId) {
            $db->table('perfil')->insert(['id' => $profileId, 'nombre' => 'Perfil ' . $profileId, 'deleted' => 0]);
        }
        $db->table('cgestion')->insert(['id' => 1, 'nombre' => 'Gestion', 'deleted' => 0]);
        $db->table('ucomercial')->insertBatch([
            ['id' => 10, 'nombre' => 'Unidad Norte', 'deleted' => 0],
            ['id' => 20, 'nombre' => 'Unidad Sur', 'deleted' => 0],
        ]);
        $db->table('sector')->insert(['id' => 1, 'nombre' => 'Retail', 'deleted' => 0]);

        $this->insertUser(1, 'Admin', 'admin@example.com', 1, 10);
        $this->insertUser(2, 'Gerente Norte', 'manager@example.com', 2, 10);
        $this->insertUser(3, 'Ejecutivo Norte', 'north@example.com', 3, 10);
        $this->insertUser(4, 'Ejecutivo Sur', 'south@example.com', 3, 20);
        $this->insertUser(5, 'Consulta', 'viewer@example.com', 5, 10);

        $db->table('cliente')->insertBatch([
            ['id' => 10, 'razon_social' => 'Cliente Norte', 'marca' => 'Alpha', 'rfc' => 'AAA010101AAA', 'sector_id' => 1, 'cgestion_id' => 1, 'ejecutivo_id' => 3, 'f_creacion' => '2026-07-01', 'deleted' => 0],
            ['id' => 11, 'razon_social' => 'Cliente Sur', 'marca' => 'Beta', 'rfc' => 'BBB010101BBB', 'sector_id' => 1, 'cgestion_id' => 1, 'ejecutivo_id' => 4, 'f_creacion' => '2026-07-02', 'deleted' => 0],
        ]);
        $db->table('cpotencial')->insertBatch([
            ['id' => 20, 'razon_social' => 'Prospecto Norte', 'marca' => 'Gamma', 'rfc' => 'CCC010101CCC', 'sector_id' => 1, 'ejecutivo_id' => 3, 'cliente_id' => null, 'f_creacion' => '2026-07-03', 'deleted' => 0],
            ['id' => 21, 'razon_social' => 'Prospecto Sur', 'marca' => 'Delta', 'rfc' => 'DDD010101DDD', 'sector_id' => 1, 'ejecutivo_id' => 4, 'cliente_id' => null, 'f_creacion' => '2026-07-04', 'deleted' => 0],
            ['id' => 22, 'razon_social' => 'Convertido Norte', 'marca' => 'Echo', 'rfc' => 'EEE010101EEE', 'sector_id' => 1, 'ejecutivo_id' => 3, 'cliente_id' => 10, 'f_creacion' => '2026-07-05', 'deleted' => 0],
        ]);
        $db->table('seguimiento')->insertBatch([
            ['tipo_id' => 1, 'cliente_id' => 10, 'f_creacion' => '2026-07-10', 'deleted' => 0],
            ['tipo_id' => 1, 'cliente_id' => 10, 'f_creacion' => '2026-07-11', 'deleted' => 0],
            ['tipo_id' => 1, 'cliente_id' => 11, 'f_creacion' => '2026-07-09', 'deleted' => 0],
            ['tipo_id' => 2, 'cliente_id' => 20, 'f_creacion' => '2026-07-12', 'deleted' => 0],
        ]);
    }

    public function testFollowUpExportUsesFiltersScopeAndSafeDownload(): void
    {
        $service = new ReportExportService();
        $export = $service->exportFollowUps($this->sessionFor(2, 2, 10)['user'], ['t' => '-1', 'u' => '-1', 'e' => '-1']);

        $this->assertMatchesRegularExpression('/^reporte-seguimiento-[0-9]{8}-[0-9]{6}\.xlsx$/', $export['filename']);
        $spreadsheet = IOFactory::load($export['path']);

        $this->assertSame(['Anunciantes', 'Clientes Potenciales'], $spreadsheet->getSheetNames());
        $this->assertSame('Cliente Norte', $spreadsheet->getSheetByName('Anunciantes')->getCell('D2')->getValue());
        $this->assertSame('2026-07-11', $spreadsheet->getSheetByName('Anunciantes')->getCell('H2')->getValue());
        $this->assertSame(null, $spreadsheet->getSheetByName('Anunciantes')->getCell('D3')->getValue());
        $this->assertSame('Prospecto Norte', $spreadsheet->getSheetByName('Clientes Potenciales')->getCell('D2')->getValue());
        $this->assertSame(null, $spreadsheet->getSheetByName('Clientes Potenciales')->getCell('D3')->getValue());
        $spreadsheet->disconnectWorksheets();

        $response = $this->withSession($this->sessionFor(2, 2, 10))->get('reporte/seguimiento/export?t=-1&u=-1&e=-1&o=1');
        $response->assertStatus(200);
        $response->response()->buildHeaders();
        $response->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('reporte-seguimiento-', $response->response()->getHeaderLine('Content-Disposition'));
    }

    public function testWalletExportOmitsFollowUpColumnAndHonorsTypeFilter(): void
    {
        $service = new ReportExportService();
        $export = $service->exportWallet($this->sessionFor(1, 1, 10)['user'], ['t' => '1', 'u' => '-1', 'e' => '-1']);
        $spreadsheet = IOFactory::load($export['path']);

        $this->assertSame(['Anunciantes'], $spreadsheet->getSheetNames());
        $sheet = $spreadsheet->getActiveSheet();
        $this->assertSame('f_creacion', $sheet->getCell('G1')->getValue());
        $this->assertSame(null, $sheet->getCell('H1')->getValue());
        $this->assertSame('Cliente Norte', $sheet->getCell('D2')->getValue());
        $this->assertSame('Cliente Sur', $sheet->getCell('D3')->getValue());
        $spreadsheet->disconnectWorksheets();
    }

    public function testFollowUpReportScreenRendersScopedRowsAndExportLink(): void
    {
        $response = $this->withSession($this->sessionFor(2, 2, 10))->get('reporte/seguimiento?t=-1&u=-1&e=-1');

        $response->assertOK();
        $response->assertSee('Reporte seguimiento');
        $response->assertSee('Cliente Norte');
        $response->assertSee('Prospecto Norte');
        $response->assertDontSee('Cliente Sur');
        $response->assertDontSee('Prospecto Sur');
        $response->assertSee('reporte/seguimiento/export');
        $response->assertSee('2026-07-11');
    }

    public function testWalletReportScreenOmitsFollowUpColumnAndHonorsTypeFilter(): void
    {
        $response = $this->withSession($this->sessionFor(1, 1, 10))->get('reporte/cartera?t=1&u=-1&e=-1');

        $response->assertOK();
        $response->assertSee('Reporte cartera');
        $response->assertSee('Cliente Norte');
        $response->assertSee('Cliente Sur');
        $response->assertDontSee('Prospecto Norte');
        $response->assertDontSee('Ultimo seguimiento');
        $response->assertSee('reporte/cartera/export');
    }

    public function testReportScreensDenyUnauthorizedProfiles(): void
    {
        $response = $this->withSession($this->sessionFor(5, 5, 10))->get('reporte/seguimiento?t=-1&u=-1&e=-1');

        $response->assertStatus(403);
    }
    public function testReportExportDeniedForUnauthorizedProfile(): void
    {
        $response = $this->withSession($this->sessionFor(5, 5, 10))->get('reporte/cartera/export?t=1&u=-1&e=-1&o=1');

        $response->assertStatus(403);
    }

    private function insertUser(int $id, string $name, string $email, int $profileId, int $unitId): void
    {
        $db = db_connect();
        $db->table('usuario')->insert([
            'id' => $id,
            'nombre' => $name,
            'usuario' => explode('@', $email)[0],
            'correo' => $email,
            'contrasenia' => password_hash('secret123', PASSWORD_DEFAULT),
            'perfil_id' => $profileId,
            'cgestion_id' => 1,
            'deleted' => 0,
        ]);
        $db->table('usuario_ucomercial')->insert(['usuario_id' => $id, 'ucomercial_id' => $unitId, 'deleted' => 0]);
    }

    /** @return array<string, mixed> */
    private function sessionFor(int $profileId, int $userId, int $unitId): array
    {
        return [
            'user' => [
                'user_id' => $userId,
                'nombre' => 'Test User',
                'correo' => 'test@example.com',
                'perfil_id' => $profileId,
                'cgestion_id' => 1,
                'ucomercial_id' => $unitId,
                'ucomercial_ids' => [$unitId],
            ],
            'permissions' => (new AuthorizationService())->permissionsForProfile($profileId),
        ];
    }
}
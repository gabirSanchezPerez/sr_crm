<?php

use App\Services\AuthorizationService;
use App\Services\DashboardService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class DashboardFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['seguimiento', 'cpotencial', 'cliente', 'usuario_ucomercial', 'usuario', 'perfil'] as $table) {
            $db->query('DROP TABLE IF EXISTS ' . $db->escapeIdentifiers($db->prefixTable($table)));
        }

        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('perfil')) . ' (id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0)');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('usuario')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, usuario TEXT NOT NULL, correo TEXT NOT NULL,
            contrasenia TEXT NOT NULL, perfil_id INTEGER NOT NULL, cgestion_id INTEGER NOT NULL, deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('usuario_ucomercial')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, usuario_id INTEGER NOT NULL, ucomercial_id INTEGER NOT NULL, deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cliente')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, razon_social TEXT NOT NULL, marca TEXT NOT NULL,
            ejecutivo_id INTEGER NOT NULL, deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cpotencial')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, razon_social TEXT NOT NULL, marca TEXT NOT NULL,
            ejecutivo_id INTEGER NOT NULL, cliente_id INTEGER NULL, deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('seguimiento')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, ejecutivo_id INTEGER NOT NULL, deleted INTEGER DEFAULT 0
        )');

        foreach ([1, 2, 3, 4, 5, 6] as $profileId) {
            $db->table('perfil')->insert(['id' => $profileId, 'nombre' => 'Perfil ' . $profileId, 'deleted' => 0]);
        }
        $this->insertUser(1, 'Admin', 'admin@example.com', 1, 10);
        $this->insertUser(2, 'Gerente Norte', 'manager@example.com', 2, 10);
        $this->insertUser(3, 'Ejecutivo Norte', 'north@example.com', 3, 10);
        $this->insertUser(4, 'Ejecutivo Sur', 'south@example.com', 3, 20);

        $db->table('cliente')->insertBatch([
            ['id' => 10, 'razon_social' => 'Cliente Norte Uno', 'marca' => 'Alpha', 'ejecutivo_id' => 3, 'deleted' => 0],
            ['id' => 11, 'razon_social' => 'Cliente Norte Dos', 'marca' => 'Alpha', 'ejecutivo_id' => 3, 'deleted' => 0],
            ['id' => 12, 'razon_social' => 'Cliente Sur', 'marca' => 'Beta', 'ejecutivo_id' => 4, 'deleted' => 0],
            ['id' => 13, 'razon_social' => 'Cliente Borrado', 'marca' => 'Zeta', 'ejecutivo_id' => 3, 'deleted' => 1],
        ]);
        $db->table('cpotencial')->insertBatch([
            ['id' => 20, 'razon_social' => 'Prospecto Norte', 'marca' => 'Gamma', 'ejecutivo_id' => 3, 'cliente_id' => null, 'deleted' => 0],
            ['id' => 21, 'razon_social' => 'Prospecto Sur', 'marca' => 'Delta', 'ejecutivo_id' => 4, 'cliente_id' => null, 'deleted' => 0],
            ['id' => 22, 'razon_social' => 'Convertido Norte', 'marca' => 'Echo', 'ejecutivo_id' => 3, 'cliente_id' => 10, 'deleted' => 0],
            ['id' => 23, 'razon_social' => 'Convertido Sur', 'marca' => 'Echo', 'ejecutivo_id' => 4, 'cliente_id' => 12, 'deleted' => 0],
        ]);
        $db->table('seguimiento')->insertBatch([
            ['id' => 30, 'ejecutivo_id' => 3, 'deleted' => 0],
            ['id' => 31, 'ejecutivo_id' => 4, 'deleted' => 0],
            ['id' => 32, 'ejecutivo_id' => 3, 'deleted' => 1],
        ]);
    }

    public function testDashboardSummaryUsesAdminScope(): void
    {
        $summary = (new DashboardService())->summary($this->sessionFor(1, 1, 10)['user']);

        $this->assertSame(3, $summary['cards']['customers']['value']);
        $this->assertSame(2, $summary['cards']['prospects']['value']);
        $this->assertSame(2, $summary['cards']['followUps']['value']);
        $this->assertSame(2, $summary['cards']['conversions']['value']);
        $this->assertSame(2, $summary['cards']['customerBrands']['value']);
        $this->assertSame(2, $summary['cards']['prospectBrands']['value']);
    }

    public function testDashboardSummaryUsesTeamScope(): void
    {
        $summary = (new DashboardService())->summary($this->sessionFor(2, 2, 10)['user']);

        $this->assertSame(2, $summary['cards']['customers']['value']);
        $this->assertSame(1, $summary['cards']['prospects']['value']);
        $this->assertSame(1, $summary['cards']['followUps']['value']);
        $this->assertSame(1, $summary['cards']['conversions']['value']);
        $this->assertSame(1, $summary['cards']['customerBrands']['value']);
        $this->assertSame(1, $summary['cards']['prospectBrands']['value']);
    }

    public function testDashboardRouteRendersMetricsAndChartAsset(): void
    {
        $response = $this->withSession($this->sessionFor(2, 2, 10))->get('home');

        $response->assertOK();
        $response->assertSee('Clientes');
        $response->assertSee('Potenciales');
        $this->assertStringContainsString('<meta name="viewport" content="width=device-width, initial-scale=1">', (string) $response->response()->getBody());
        $response->assertSee('nxl-navigation');
        $response->assertSee('assets/css/bootstrap.min.css');
        $response->assertSee('assets/css/theme.min.css');
        $response->assertSee('assets/js/common-init.min.js');
        $response->assertSee('assets/vendors/js/apexcharts.min.js');
        $response->assertSee('dashboardSummaryChart');
        $response->assertSee('forecastChart');
        $response->assertSee('Forecast');
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

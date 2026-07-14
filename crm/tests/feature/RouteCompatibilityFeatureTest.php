<?php

use App\Services\AuthorizationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class RouteCompatibilityFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['cpotencial', 'cliente', 'usuario_ucomercial', 'usuario', 'perfil'] as $table) {
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

        foreach ([1, 2, 3, 4, 5, 6] as $profileId) {
            $db->table('perfil')->insert(['id' => $profileId, 'nombre' => 'Perfil ' . $profileId, 'deleted' => 0]);
        }
        $this->insertUser(1, 'Admin', 'admin@example.com', 1, 10, 1);
        $this->insertUser(2, 'Gerente Norte', 'manager@example.com', 2, 10, 1);
        $this->insertUser(3, 'Ejecutivo Norte', 'north@example.com', 3, 10, 1);
        $this->insertUser(4, 'Ejecutivo Sur', 'south@example.com', 3, 20, 2);

        $db->table('cliente')->insertBatch([
            ['id' => 10, 'razon_social' => 'Cliente Norte', 'marca' => 'Alpha', 'ejecutivo_id' => 3, 'deleted' => 0],
            ['id' => 11, 'razon_social' => 'Cliente Sur', 'marca' => 'Beta', 'ejecutivo_id' => 4, 'deleted' => 0],
        ]);
        $db->table('cpotencial')->insertBatch([
            ['id' => 20, 'razon_social' => 'Prospecto Norte', 'marca' => 'Gamma', 'ejecutivo_id' => 3, 'cliente_id' => null, 'deleted' => 0],
            ['id' => 21, 'razon_social' => 'Prospecto Sur', 'marca' => 'Delta', 'ejecutivo_id' => 4, 'cliente_id' => null, 'deleted' => 0],
            ['id' => 22, 'razon_social' => 'Convertido Norte', 'marca' => 'Echo', 'ejecutivo_id' => 3, 'cliente_id' => 10, 'deleted' => 0],
        ]);
    }

    public function testLegacySearchScreenIsPreserved(): void
    {
        $response = $this->withSession($this->sessionFor(2, 2, 10))->get('search');

        $response->assertOK();
        $response->assertSee('Busqueda de cuentas');
        $response->assertSee('auth/searchClient');
    }

    public function testLegacySearchClientReturnsScopedFilteredRows(): void
    {
        $response = $this->withSession($this->sessionFor(2, 2, 10))->post('auth/searchClient', $this->withCsrf([
            'draw' => 9,
            'start' => 0,
            'length' => 10,
            'r' => 'Norte',
            'm' => '',
            't' => '-1',
        ]));

        $response->assertOK();
        $payload = json_decode((string) $response->getJSON(), true);
        $this->assertSame(9, $payload['draw']);
        $this->assertSame(2, $payload['recordsFiltered']);
        $this->assertSame('Cliente Norte', $payload['data'][0]['razon_social']);
        $this->assertSame('Prospecto Norte', $payload['data'][1]['razon_social']);
    }

    public function testLegacySearchClientEscapesStoredDisplayValues(): void
    {
        $this->insertUser(5, '<script>alert(1)</script>', 'xss@example.com', 3, 10, 1);
        db_connect()->table('cliente')->insert([
            'id' => 12,
            'razon_social' => 'Bad <script>alert(2)</script>',
            'marca' => '<img src=x onerror=alert(3)>',
            'ejecutivo_id' => 5,
            'deleted' => 0,
        ]);

        $response = $this->withSession($this->sessionFor(2, 2, 10))->post('auth/searchClient', $this->withCsrf([
            'draw' => 10,
            'start' => 0,
            'length' => 10,
            'r' => 'Bad',
            'm' => '',
            't' => '1',
        ]));

        $response->assertOK();
        $json = (string) $response->getJSON();
        $this->assertStringNotContainsString('<script>', $json);
        $this->assertStringNotContainsString('<img src=x onerror=alert(3)>', $json);
        $this->assertStringContainsString('&lt;script&gt;alert(2)&lt;/script&gt;', $json);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(3)&gt;', $json);
    }
    public function testLegacyExistenceChecksPreserveTypoAndReturnDuplicateCount(): void
    {
        $customer = $this->withSession($this->sessionFor(1, 1, 10))->post('cliente/vericarExistencia', $this->withCsrf([
            'razon' => 'Cliente Norte',
            'marca' => 'Alpha',
            'ejecutivo' => 3,
        ]));
        $prospect = $this->withSession($this->sessionFor(1, 1, 10))->post('cpotencial/vericarExistencia', $this->withCsrf([
            'razon' => 'Prospecto Norte',
            'marca' => 'Gamma',
            'ejecutivo' => 3,
        ]));

        $customer->assertOK();
        $prospect->assertOK();
        $this->assertSame(1, json_decode((string) $customer->getJSON(), true));
        $this->assertSame(1, json_decode((string) $prospect->getJSON(), true));
    }

    public function testLegacyExistenceCheckUsesBoundInput(): void
    {
        $response = $this->withSession($this->sessionFor(1, 1, 10))->post('cliente/vericarExistencia', $this->withCsrf([
            'razon' => "Cliente Norte' OR 1=1 --",
            'marca' => 'Alpha',
            'ejecutivo' => 3,
        ]));

        $response->assertOK();
        $this->assertSame(0, json_decode((string) $response->response()->getBody(), true));
    }
    private function insertUser(int $id, string $name, string $email, int $profileId, int $unitId, int $managementId): void
    {
        $db = db_connect();
        $db->table('usuario')->insert([
            'id' => $id,
            'nombre' => $name,
            'usuario' => explode('@', $email)[0],
            'correo' => $email,
            'contrasenia' => password_hash('secret123', PASSWORD_DEFAULT),
            'perfil_id' => $profileId,
            'cgestion_id' => $managementId,
            'deleted' => 0,
        ]);
        $db->table('usuario_ucomercial')->insert(['usuario_id' => $id, 'ucomercial_id' => $unitId, 'deleted' => 0]);
    }

    /** @return array<string, mixed> */
    private function withCsrf(array $params): array
    {
        $security = Services::security();
        $params[$security->getTokenName()] = $security->getHash();

        return $params;
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
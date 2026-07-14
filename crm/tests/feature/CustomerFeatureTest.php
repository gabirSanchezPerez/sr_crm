<?php

use App\Services\AuthorizationService;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class CustomerFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['cliente', 'usuario_ucomercial', 'usuario', 'sector', 'ucomercial', 'cgestion', 'perfil'] as $table) {
            $db->query('DROP TABLE IF EXISTS ' . $db->escapeIdentifiers($db->prefixTable($table)));
        }

        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('perfil')) . ' (
            id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cgestion')) . ' (
            id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('ucomercial')) . ' (
            id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('sector')) . ' (
            id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0
        )');
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
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            razon_social TEXT NOT NULL,
            rfc TEXT NULL,
            sector_id INTEGER NULL,
            cpotencial_id INTEGER NULL,
            marca TEXT NOT NULL,
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

        foreach ([1, 2, 3, 4, 5, 6] as $profileId) {
            $db->table('perfil')->insert(['id' => $profileId, 'nombre' => 'Perfil ' . $profileId, 'deleted' => 0]);
        }
        $db->table('cgestion')->insertBatch([
            ['id' => 1, 'nombre' => 'Gestion Norte', 'deleted' => 0],
            ['id' => 2, 'nombre' => 'Gestion Sur', 'deleted' => 0],
        ]);
        $db->table('ucomercial')->insertBatch([
            ['id' => 10, 'nombre' => 'Unidad Norte', 'deleted' => 0],
            ['id' => 20, 'nombre' => 'Unidad Sur', 'deleted' => 0],
        ]);
        $db->table('sector')->insertBatch([
            ['id' => 1, 'nombre' => 'Retail', 'deleted' => 0],
            ['id' => 2, 'nombre' => 'Servicios', 'deleted' => 0],
        ]);

        $this->insertUser(1, 'Administrador', 1, 10, 1);
        $this->insertUser(2, 'Gerente Norte', 2, 10, 1);
        $this->insertUser(3, 'Ejecutivo Norte', 3, 10, 1);
        $this->insertUser(4, 'Ejecutivo Sur', 3, 20, 2);

        $db->table('cliente')->insertBatch([
            ['id' => 1, 'razon_social' => 'Cliente Norte', 'marca' => 'Marca Norte', 'rfc' => 'NRT010101AAA', 'sector_id' => 1, 'cgestion_id' => 1, 'ejecutivo_id' => 3, 'deleted' => 0, '_countries_id' => 42, 'u_crea' => 1],
            ['id' => 2, 'razon_social' => 'Cliente Sur', 'marca' => 'Marca Sur', 'rfc' => 'SUR010101AAA', 'sector_id' => 2, 'cgestion_id' => 2, 'ejecutivo_id' => 4, 'deleted' => 0, '_countries_id' => 42, 'u_crea' => 1],
            ['id' => 3, 'razon_social' => 'Cliente Eliminado', 'marca' => 'Marca Eliminada', 'rfc' => 'DEL010101AAA', 'sector_id' => 1, 'cgestion_id' => 1, 'ejecutivo_id' => 3, 'deleted' => 1, '_countries_id' => 42, 'u_crea' => 1],
        ]);
    }

    public function testCustomerListRendersOnlyActiveRowsInScope(): void
    {
        $admin = $this->withSession($this->sessionFor(1, 1, 10))->get('cliente');
        $admin->assertOK();
        $admin->assertSee('Cliente Norte');
        $admin->assertSee('Cliente Sur');
        $admin->assertDontSee('Cliente Eliminado');

        $owner = $this->withSession($this->sessionFor(3, 3, 10))->get('cliente');
        $owner->assertOK();
        $owner->assertSee('Cliente Norte');
        $owner->assertDontSee('Cliente Sur');

        $team = $this->withSession($this->sessionFor(2, 2, 10))->get('cliente');
        $team->assertOK();
        $team->assertSee('Cliente Norte');
        $team->assertDontSee('Cliente Sur');
    }

    public function testCustomerRowsEndpointSearchesAndReturnsAuthorizedActions(): void
    {
        $response = $this->withSession($this->sessionFor(1, 1, 10))->post('cliente/get_rows', $this->withCsrf([
            'draw' => '8',
            'search' => ['value' => 'Gestion Norte'],
        ]));

        $response->assertOK();
        $response->assertJSONFragment(['draw' => 8, 'recordsTotal' => 2, 'recordsFiltered' => 1]);
        $payload = json_decode((string) $response->getJSON(), true);
        $this->assertSame('Cliente Norte', $payload['data'][0]['razon_social']);
        $this->assertSame(['edit', 'delete'], array_column($payload['data'][0]['_actions'], 'name'));
        $this->assertStringNotContainsString('<button', json_encode($payload['data'][0]['_actions']));
    }

    public function testCustomerRowsEndpointPaginatesAndOrdersAuthorizedResults(): void
    {
        $response = $this->withSession($this->sessionFor(1, 1, 10))->post('cliente/get_rows', $this->withCsrf([
            'draw' => '9',
            'start' => '1',
            'length' => '1',
            'order' => [0 => ['column' => '0', 'dir' => 'asc']],
        ]));

        $response->assertOK();
        $payload = json_decode((string) $response->getJSON(), true);
        $this->assertSame(9, $payload['draw']);
        $this->assertSame(2, $payload['recordsTotal']);
        $this->assertSame(2, $payload['recordsFiltered']);
        $this->assertCount(1, $payload['data']);
        $this->assertSame('Cliente Sur', $payload['data'][0]['razon_social']);
    }

    public function testCustomerCreateEditAndSoftDeletePersistAuditFields(): void
    {
        $create = $this->withSession($this->sessionFor(1, 1, 10))->post('cliente/add', $this->withCsrf([
            'razon_social' => ' Nuevo Cliente ',
            'marca' => ' Nueva Marca ',
            'rfc' => ' cli010101aa1 ',
            'sector_id' => 1,
            'ejecutivo_id' => 3,
            'estado' => 'CDMX',
            'ciudad' => 'Ciudad de Mexico',
            'cp' => '01234',
            'direccion' => 'Calle 1',
        ]));

        $created = db_connect()->table('cliente')->where('razon_social', 'Nuevo Cliente')->get()->getRowArray();
        $this->assertNotNull($created);
        $this->assertSame('Nueva Marca', $created['marca']);
        $this->assertSame('CLI010101AA1', $created['rfc']);
        $this->assertSame(1, (int) $created['cgestion_id']);
        $this->assertSame(42, (int) $created['_countries_id']);
        $this->assertSame(1, (int) $created['u_crea']);
        $this->assertNotEmpty($created['f_creacion']);
        $create->assertRedirectTo('cliente/' . $created['id']);

        $edit = $this->withSession($this->sessionFor(1, 1, 10))->post('cliente/' . $created['id'], $this->withCsrf([
            'razon_social' => 'Cliente Actualizado',
            'marca' => 'Marca Actualizada',
            'rfc' => 'UPD010101AA1',
            'sector_id' => 2,
            'ejecutivo_id' => 4,
        ]));

        $updated = db_connect()->table('cliente')->where('id', $created['id'])->get()->getRowArray();
        $this->assertSame('Cliente Actualizado', $updated['razon_social']);
        $this->assertSame(2, (int) $updated['cgestion_id']);
        $this->assertSame(1, (int) $updated['u_modifica']);
        $this->assertNotEmpty($updated['f_modificacion']);
        $edit->assertRedirectTo('cliente/' . $created['id']);

        $delete = $this->withSession($this->sessionFor(1, 1, 10))->post('cliente/delete/' . $created['id'], $this->withCsrf([]));
        $deleted = db_connect()->table('cliente')->where('id', $created['id'])->get()->getRowArray();
        $this->assertSame(1, (int) $deleted['deleted']);
        $this->assertSame(1, (int) $deleted['u_modifica']);
        $delete->assertJSONFragment(['exito' => true]);
    }

    public function testCustomerRejectsDuplicateAndUnauthorizedMutationBeforeDataChanges(): void
    {
        $duplicate = $this->withSession($this->sessionFor(1, 1, 10))->post('cliente/add', $this->withCsrf([
            'razon_social' => 'Cliente Norte',
            'marca' => 'Marca Norte',
            'rfc' => 'DUP010101AAA',
            'sector_id' => 1,
            'ejecutivo_id' => 3,
        ]));
        $duplicate->assertOK();
        $this->assertSame(1, (int) db_connect()->table('cliente')->where('razon_social', 'Cliente Norte')->where('marca', 'Marca Norte')->where('cgestion_id', 1)->where('deleted', 0)->countAllResults());

        $unauthorized = $this->withSession($this->sessionFor(3, 3, 10))->post('cliente/add', $this->withCsrf([
            'razon_social' => 'No Autorizado',
            'marca' => 'Marca No Autorizada',
            'sector_id' => 1,
            'ejecutivo_id' => 3,
        ]));
        $unauthorized->assertStatus(403);
        $this->assertSame(0, (int) db_connect()->table('cliente')->where('razon_social', 'No Autorizado')->countAllResults());
    }

    public function testCustomerCsrfRejectsMutationBeforeDataChanges(): void
    {
        try {
            $this->withSession($this->sessionFor(1, 1, 10))->post('cliente/add', [
                'razon_social' => 'Sin Token',
                'marca' => 'Marca Sin Token',
                'sector_id' => 1,
                'ejecutivo_id' => 3,
            ]);
            $this->fail('Expected CSRF validation to reject the mutation.');
        } catch (SecurityException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, (int) db_connect()->table('cliente')->where('razon_social', 'Sin Token')->countAllResults());
    }

    private function insertUser(int $id, string $name, int $profileId, int $unitId, int $managementId): void
    {
        $db = db_connect();
        $db->table('usuario')->insert([
            'id' => $id,
            'nombre' => $name,
            'usuario' => 'user' . $id,
            'correo' => 'user' . $id . '@example.com',
            'contrasenia' => password_hash('secret123', PASSWORD_DEFAULT),
            'perfil_id' => $profileId,
            'cgestion_id' => $managementId,
            'deleted' => 0,
        ]);
        $db->table('usuario_ucomercial')->insert([
            'usuario_id' => $id,
            'ucomercial_id' => $unitId,
            'deleted' => 0,
        ]);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function withCsrf(array $params): array
    {
        $security = Services::security();
        $params[$security->getTokenName()] = $security->getHash();

        return $params;
    }

    /**
     * @return array<string, mixed>
     */
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

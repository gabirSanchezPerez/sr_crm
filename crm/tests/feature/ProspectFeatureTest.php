<?php

use App\Services\AuthorizationService;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class ProspectFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['cliente', 'cpotencial', 'usuario_ucomercial', 'usuario', 'sector', 'ucomercial', 'cgestion', 'perfil'] as $table) {
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
        $db->table('cgestion')->insert(['id' => 1, 'nombre' => 'Gestion', 'deleted' => 0]);
        $db->table('ucomercial')->insertBatch([
            ['id' => 10, 'nombre' => 'Unidad Norte', 'deleted' => 0],
            ['id' => 20, 'nombre' => 'Unidad Sur', 'deleted' => 0],
        ]);
        $db->table('sector')->insertBatch([
            ['id' => 1, 'nombre' => 'Retail', 'deleted' => 0],
            ['id' => 2, 'nombre' => 'Servicios', 'deleted' => 0],
        ]);

        $this->insertUser(1, 'Administrador', 1, 10);
        $this->insertUser(2, 'Gerente Norte', 2, 10);
        $this->insertUser(3, 'Ejecutivo Norte', 3, 10);
        $this->insertUser(4, 'Ejecutivo Sur', 3, 20);
        $this->insertUser(5, 'Editor Comercial', 4, 10);

        $db->table('cpotencial')->insertBatch([
            ['id' => 1, 'razon_social' => 'Prospecto Norte', 'marca' => 'Marca Norte', 'rfc' => 'NRT010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 3, 'cliente_id' => null, 'deleted' => 0, '_countries_id' => 42, 'u_crea' => 1],
            ['id' => 2, 'razon_social' => 'Prospecto Sur', 'marca' => 'Marca Sur', 'rfc' => 'SUR010101AAA', 'sector_id' => 2, 'ejecutivo_id' => 4, 'cliente_id' => null, 'deleted' => 0, '_countries_id' => 42, 'u_crea' => 1],
            ['id' => 3, 'razon_social' => 'Prospecto Convertido', 'marca' => 'Marca Convertida', 'rfc' => 'CON010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 3, 'cliente_id' => 9, 'deleted' => 0, '_countries_id' => 42, 'u_crea' => 1],
            ['id' => 4, 'razon_social' => 'Prospecto Eliminado', 'marca' => 'Marca Eliminada', 'rfc' => 'DEL010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 3, 'cliente_id' => null, 'deleted' => 1, '_countries_id' => 42, 'u_crea' => 1],
        ]);
    }

    public function testProspectListRendersOnlyActiveUnconvertedRowsInScope(): void
    {
        $admin = $this->withSession($this->sessionFor(1, 1, 10))->get('cpotencial');

        $admin->assertOK();
        $admin->assertSee('Prospectos');
        $admin->assertSee('Prospecto Norte');
        $admin->assertSee('Prospecto Sur');
        $admin->assertDontSee('Prospecto Convertido');
        $admin->assertDontSee('Prospecto Eliminado');

        $owner = $this->withSession($this->sessionFor(3, 3, 10))->get('cpotencial');
        $owner->assertOK();
        $owner->assertSee('Prospecto Norte');
        $owner->assertDontSee('Prospecto Sur');

        $team = $this->withSession($this->sessionFor(2, 2, 10))->get('cpotencial');
        $team->assertOK();
        $team->assertSee('Prospecto Norte');
        $team->assertDontSee('Prospecto Sur');
    }

    public function testProspectRowsEndpointSearchesAndReturnsAuthorizedActions(): void
    {
        $response = $this->withSession($this->sessionFor(4, 5, 10))->post('cpotencial/get_rows', $this->withCsrf([
            'draw' => '7',
            'search' => ['value' => 'Norte'],
        ]));

        $response->assertOK();
        $response->assertJSONFragment(['draw' => 7, 'recordsTotal' => 2, 'recordsFiltered' => 1]);
        $payload = json_decode((string) $response->getJSON(), true);
        $this->assertSame('Prospecto Norte', $payload['data'][0]['razon_social']);
        $this->assertSame(['edit', 'convert', 'delete'], array_column($payload['data'][0]['_actions'], 'name'));
        $this->assertStringNotContainsString('<button', json_encode($payload['data'][0]['_actions']));
    }

    public function testProspectCreateEditAndSoftDeletePersistAuditFields(): void
    {
        $create = $this->withSession($this->sessionFor(4, 5, 10))->post('cpotencial/add', $this->withCsrf([
            'razon_social' => ' Nuevo Prospecto ',
            'marca' => ' Nueva Marca ',
            'rfc' => ' abc010101aa1 ',
            'sector_id' => 1,
            'ejecutivo_id' => 3,
            'estado' => 'CDMX',
            'ciudad' => 'Ciudad de Mexico',
            'cp' => '01234',
            'direccion' => 'Calle 1',
        ]));

        $created = db_connect()->table('cpotencial')->where('razon_social', 'Nuevo Prospecto')->get()->getRowArray();
        $this->assertNotNull($created);
        $this->assertSame('Nueva Marca', $created['marca']);
        $this->assertSame('ABC010101AA1', $created['rfc']);
        $this->assertSame(42, (int) $created['_countries_id']);
        $this->assertSame(5, (int) $created['u_crea']);
        $this->assertNotEmpty($created['f_creacion']);
        $create->assertRedirectTo('cpotencial/' . $created['id']);

        $edit = $this->withSession($this->sessionFor(4, 5, 10))->post('cpotencial/' . $created['id'], $this->withCsrf([
            'razon_social' => 'Prospecto Actualizado',
            'marca' => 'Marca Actualizada',
            'rfc' => 'UPD010101AA1',
            'sector_id' => 2,
            'ejecutivo_id' => 3,
        ]));

        $updated = db_connect()->table('cpotencial')->where('id', $created['id'])->get()->getRowArray();
        $this->assertSame('Prospecto Actualizado', $updated['razon_social']);
        $this->assertSame(5, (int) $updated['u_modifica']);
        $this->assertNotEmpty($updated['f_modificacion']);
        $edit->assertRedirectTo('cpotencial/' . $created['id']);

        $delete = $this->withSession($this->sessionFor(4, 5, 10))->post('cpotencial/delete/' . $created['id'], $this->withCsrf([]));
        $deleted = db_connect()->table('cpotencial')->where('id', $created['id'])->get()->getRowArray();
        $this->assertSame(1, (int) $deleted['deleted']);
        $this->assertSame(5, (int) $deleted['u_modifica']);
        $delete->assertJSONFragment(['exito' => true]);
    }

    public function testProspectRejectsDuplicateAndUnauthorizedMutationBeforeDataChanges(): void
    {
        $duplicate = $this->withSession($this->sessionFor(4, 5, 10))->post('cpotencial/add', $this->withCsrf([
            'razon_social' => 'Prospecto Norte',
            'marca' => 'Marca Norte',
            'rfc' => 'DUP010101AAA',
            'sector_id' => 1,
            'ejecutivo_id' => 3,
        ]));
        $duplicate->assertOK();
        $this->assertSame(1, (int) db_connect()->table('cpotencial')->where('razon_social', 'Prospecto Norte')->where('marca', 'Marca Norte')->where('deleted', 0)->countAllResults());

        $unauthorized = $this->withSession($this->sessionFor(3, 3, 10))->post('cpotencial/add', $this->withCsrf([
            'razon_social' => 'No Autorizado',
            'marca' => 'Marca No Autorizada',
            'sector_id' => 1,
            'ejecutivo_id' => 3,
        ]));
        $unauthorized->assertStatus(403);
        $this->assertSame(0, (int) db_connect()->table('cpotencial')->where('razon_social', 'No Autorizado')->countAllResults());
    }

    public function testProspectCsrfRejectsMutationBeforeDataChanges(): void
    {
        try {
            $this->withSession($this->sessionFor(4, 5, 10))->post('cpotencial/add', [
                'razon_social' => 'Sin Token',
                'marca' => 'Marca Sin Token',
                'sector_id' => 1,
                'ejecutivo_id' => 3,
            ]);
            $this->fail('Expected CSRF validation to reject the mutation.');
        } catch (SecurityException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, (int) db_connect()->table('cpotencial')->where('razon_social', 'Sin Token')->countAllResults());
    }

    public function testProspectConversionCreatesCustomerAndLinksProspectTransactionally(): void
    {
        $response = $this->withSession($this->sessionFor(4, 5, 10))->post('cpotencial/convert/1', $this->withCsrf([]));

        $response->assertOK();
        $payload = json_decode((string) $response->getJSON(), true);
        $this->assertTrue($payload['exito']);
        $this->assertTrue($payload['created']);
        $this->assertGreaterThan(0, (int) $payload['cliente_id']);

        $db = db_connect();
        $customer = $db->table('cliente')->where('id', $payload['cliente_id'])->get()->getRowArray();
        $this->assertNotNull($customer);
        $this->assertSame('Prospecto Norte', $customer['razon_social']);
        $this->assertSame('Marca Norte', $customer['marca']);
        $this->assertSame('NRT010101AAA', $customer['rfc']);
        $this->assertSame(1, (int) $customer['sector_id']);
        $this->assertSame(1, (int) $customer['cpotencial_id']);
        $this->assertSame(1, (int) $customer['cgestion_id']);
        $this->assertSame(3, (int) $customer['ejecutivo_id']);
        $this->assertSame(5, (int) $customer['u_crea']);
        $this->assertNotEmpty($customer['f_creacion']);

        $prospect = $db->table('cpotencial')->where('id', 1)->get()->getRowArray();
        $this->assertSame((int) $payload['cliente_id'], (int) $prospect['cliente_id']);
        $this->assertSame(5, (int) $prospect['u_modifica']);
        $this->assertNotEmpty($prospect['f_modificacion']);

        $list = $this->withSession($this->sessionFor(1, 1, 10))->get('cpotencial');
        $list->assertOK();
        $list->assertDontSee('Prospecto Norte');
    }

    public function testProspectConversionLinksDuplicateCustomerWithoutCreatingAnotherOne(): void
    {
        $db = db_connect();
        $db->table('cliente')->insert([
            'id' => 77,
            'razon_social' => 'Prospecto Norte',
            'marca' => 'Marca Norte',
            'rfc' => 'NRT010101AAA',
            'sector_id' => 1,
            'cpotencial_id' => null,
            'cgestion_id' => 1,
            'ejecutivo_id' => 3,
            'deleted' => 0,
        ]);

        $response = $this->withSession($this->sessionFor(4, 5, 10))->post('cpotencial/convert/1', $this->withCsrf([]));

        $response->assertOK();
        $response->assertJSONFragment(['exito' => true, 'cliente_id' => 77, 'created' => false]);
        $this->assertSame(1, (int) $db->table('cliente')->where('razon_social', 'Prospecto Norte')->where('marca', 'Marca Norte')->where('deleted', 0)->countAllResults());
        $prospect = $db->table('cpotencial')->where('id', 1)->get()->getRowArray();
        $this->assertSame(77, (int) $prospect['cliente_id']);
    }

    public function testProspectConversionRejectsInvalidOrUnauthorizedRequestsBeforeDataChanges(): void
    {
        $db = db_connect();
        $db->table('cpotencial')->insert([
            'id' => 5,
            'razon_social' => 'Prospecto Sin RFC',
            'marca' => 'Marca Sin RFC',
            'rfc' => '',
            'sector_id' => 1,
            'ejecutivo_id' => 3,
            'cliente_id' => null,
            'deleted' => 0,
            '_countries_id' => 42,
            'u_crea' => 1,
        ]);
        $db->table('cpotencial')->insert([
            'id' => 6,
            'razon_social' => 'Prospecto Sin Ejecutivo',
            'marca' => 'Marca Sin Ejecutivo',
            'rfc' => 'SNE010101AAA',
            'sector_id' => 1,
            'ejecutivo_id' => 99,
            'cliente_id' => null,
            'deleted' => 0,
            '_countries_id' => 42,
            'u_crea' => 1,
        ]);

        $unauthorized = $this->withSession($this->sessionFor(3, 3, 10))->post('cpotencial/convert/1', $this->withCsrf([]));
        $unauthorized->assertStatus(403);
        $this->assertNull($db->table('cpotencial')->select('cliente_id')->where('id', 1)->get()->getRowArray()['cliente_id']);

        $emptyRfc = $this->withSession($this->sessionFor(4, 5, 10))->post('cpotencial/convert/5', $this->withCsrf([]));
        $emptyRfc->assertStatus(422);
        $this->assertNull($db->table('cpotencial')->select('cliente_id')->where('id', 5)->get()->getRowArray()['cliente_id']);

        $missingExecutive = $this->withSession($this->sessionFor(1, 1, 10))->post('cpotencial/convert/6', $this->withCsrf([]));
        $missingExecutive->assertStatus(422);
        $this->assertNull($db->table('cpotencial')->select('cliente_id')->where('id', 6)->get()->getRowArray()['cliente_id']);

        $alreadyConverted = $this->withSession($this->sessionFor(1, 1, 10))->post('cpotencial/convert/3', $this->withCsrf([]));
        $alreadyConverted->assertStatus(404);
        $this->assertSame(0, (int) $db->table('cliente')->countAllResults());
    }
    private function insertUser(int $id, string $name, int $profileId, int $unitId): void
    {
        $db = db_connect();
        $db->table('usuario')->insert([
            'id' => $id,
            'nombre' => $name,
            'usuario' => 'user' . $id,
            'correo' => 'user' . $id . '@example.com',
            'contrasenia' => password_hash('secret123', PASSWORD_DEFAULT),
            'perfil_id' => $profileId,
            'cgestion_id' => 1,
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


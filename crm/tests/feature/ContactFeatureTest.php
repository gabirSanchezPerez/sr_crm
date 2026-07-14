<?php

use App\Services\AuthorizationService;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class ContactFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['documento', 'contacto', 'cpotencial', 'cliente', 'usuario_ucomercial', 'usuario', 'sector', 'ucomercial', 'cgestion', 'perfil'] as $table) {
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
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('contacto')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER NULL,
            cpotencial_id INTEGER NULL,
            nombre TEXT NOT NULL,
            telefono TEXT NOT NULL,
            celular TEXT NULL,
            otro_num TEXT NULL,
            puesto TEXT NULL,
            departamento TEXT NULL,
            correo TEXT NOT NULL,
            descripcion TEXT NULL,
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
        )');        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('documento')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            cliente_id INTEGER NULL,
            cpotencial_id INTEGER NULL,
            nombre TEXT NOT NULL,
            archivo_original TEXT NOT NULL,
            archivo_ruta TEXT NOT NULL,
            mime TEXT NULL,
            tamano INTEGER DEFAULT 0,
            u_crea INTEGER NULL,
            u_modifica INTEGER NULL,
            f_creacion TEXT NULL,
            f_modificacion TEXT NULL,
            deleted INTEGER DEFAULT 0
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

        $this->insertUser(1, 'Administrador', 1, 10);
        $this->insertUser(2, 'Gerente Norte', 2, 10);
        $this->insertUser(3, 'Ejecutivo Norte', 3, 10);
        $this->insertUser(4, 'Ejecutivo Sur', 3, 20);
        $this->insertUser(5, 'Consulta', 4, 10);

        $db->table('cliente')->insertBatch([
            ['id' => 10, 'razon_social' => 'Cliente Norte', 'marca' => 'Marca Norte', 'rfc' => 'CLN010101AAA', 'sector_id' => 1, 'cgestion_id' => 1, 'ejecutivo_id' => 3, 'deleted' => 0],
            ['id' => 11, 'razon_social' => 'Cliente Sur', 'marca' => 'Marca Sur', 'rfc' => 'CLS010101AAA', 'sector_id' => 1, 'cgestion_id' => 1, 'ejecutivo_id' => 4, 'deleted' => 0],
            ['id' => 12, 'razon_social' => 'Cliente Eliminado', 'marca' => 'Marca Baja', 'rfc' => 'CLE010101AAA', 'sector_id' => 1, 'cgestion_id' => 1, 'ejecutivo_id' => 3, 'deleted' => 1],
        ]);
        $db->table('cpotencial')->insertBatch([
            ['id' => 20, 'razon_social' => 'Prospecto Norte', 'marca' => 'Marca Norte', 'rfc' => 'PRN010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 3, 'cliente_id' => null, 'deleted' => 0],
            ['id' => 21, 'razon_social' => 'Prospecto Sur', 'marca' => 'Marca Sur', 'rfc' => 'PRS010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 4, 'cliente_id' => null, 'deleted' => 0],
            ['id' => 22, 'razon_social' => 'Prospecto Convertido', 'marca' => 'Marca Conv', 'rfc' => 'PRC010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 3, 'cliente_id' => 10, 'deleted' => 0],
        ]);
        $db->table('contacto')->insertBatch([
            ['id' => 100, 'cliente_id' => 10, 'cpotencial_id' => null, 'nombre' => 'Contacto Cliente Norte', 'telefono' => '5511111111', 'celular' => '5522222222', 'otro_num' => null, 'puesto' => 'Compras', 'departamento' => 'Comercial', 'correo' => 'norte@example.com', 'descripcion' => null, 'u_crea' => 1, 'deleted' => 0, '_countries_id' => 42],
            ['id' => 101, 'cliente_id' => 11, 'cpotencial_id' => null, 'nombre' => 'Contacto Cliente Sur', 'telefono' => '5533333333', 'celular' => '5544444444', 'otro_num' => null, 'puesto' => null, 'departamento' => null, 'correo' => 'sur@example.com', 'descripcion' => null, 'u_crea' => 1, 'deleted' => 0, '_countries_id' => 42],
            ['id' => 102, 'cliente_id' => null, 'cpotencial_id' => 20, 'nombre' => 'Contacto Prospecto Norte', 'telefono' => '5555555555', 'celular' => '5566666666', 'otro_num' => null, 'puesto' => null, 'departamento' => null, 'correo' => 'prospecto@example.com', 'descripcion' => null, 'u_crea' => 1, 'deleted' => 0, '_countries_id' => 42],
            ['id' => 103, 'cliente_id' => 12, 'cpotencial_id' => null, 'nombre' => 'Contacto Padre Eliminado', 'telefono' => '5577777777', 'celular' => null, 'otro_num' => null, 'puesto' => null, 'departamento' => null, 'correo' => 'deleted@example.com', 'descripcion' => null, 'u_crea' => 1, 'deleted' => 0, '_countries_id' => 42],
            ['id' => 104, 'cliente_id' => 10, 'cpotencial_id' => null, 'nombre' => 'Contacto Eliminado', 'telefono' => '5588888888', 'celular' => null, 'otro_num' => null, 'puesto' => null, 'departamento' => null, 'correo' => 'contactodel@example.com', 'descripcion' => null, 'u_crea' => 1, 'deleted' => 1, '_countries_id' => 42],
        ]);
    }

    public function testContactListAndRowsRespectParentScopeAndHideInactiveParents(): void
    {
        $admin = $this->withSession($this->sessionFor(1, 1, 10))->get('contacto');
        $admin->assertOK();
        $admin->assertSee('Contacto Cliente Norte');
        $admin->assertSee('Contacto Cliente Sur');
        $admin->assertSee('Contacto Prospecto Norte');
        $admin->assertDontSee('Contacto Padre Eliminado');
        $admin->assertDontSee('Contacto Eliminado');

        $team = $this->withSession($this->sessionFor(2, 2, 10))->post('contacto/get_rows', $this->withCsrf([
            'draw' => '4',
            'search' => ['value' => 'Contacto'],
        ]));
        $team->assertOK();
        $payload = json_decode((string) $team->getJSON(), true);
        $this->assertSame(2, $payload['recordsTotal']);
        $this->assertSame(['Contacto Prospecto Norte', 'Contacto Cliente Norte'], array_column($payload['data'], 'nombre'));
        $this->assertSame(['edit', 'delete'], array_column($payload['data'][0]['_actions'], 'name'));
    }

    public function testContactCreateEditDeleteAndSubpanelPersistAuditFields(): void
    {
        $create = $this->withSession($this->sessionFor(2, 2, 10))->post('contacto/add', $this->withCsrf([
            'cliente_id' => '10_1',
            'nombre' => ' Nuevo Contacto ',
            'telefono' => '5599999999',
            'celular' => '5512345678',
            'correo' => 'NUEVO@EXAMPLE.COM',
            'puesto' => 'Direccion',
            'departamento' => 'Ventas',
            'descripcion' => 'Alta desde formulario',
        ]));

        $create->assertRedirect();
        $created = db_connect()->table('contacto')->where('nombre', 'Nuevo Contacto')->get()->getRowArray();
        $this->assertNotNull($created);
        $this->assertSame(10, (int) $created['cliente_id']);
        $this->assertNull($created['cpotencial_id']);
        $this->assertSame('nuevo@example.com', $created['correo']);
        $this->assertSame(2, (int) $created['u_crea']);
        $this->assertNotEmpty($created['f_creacion']);
        $create->assertRedirectTo('contacto/' . $created['id']);

        $edit = $this->withSession($this->sessionFor(2, 2, 10))->post('contacto/' . $created['id'], $this->withCsrf([
            'cliente_id' => '10_1',
            'nombre' => 'Contacto Actualizado',
            'telefono' => '5500000000',
            'celular' => '5512345678',
            'correo' => 'actualizado@example.com',
        ]));
        $updated = db_connect()->table('contacto')->where('id', $created['id'])->get()->getRowArray();
        $this->assertSame('Contacto Actualizado', $updated['nombre']);
        $this->assertSame(2, (int) $updated['u_modifica']);
        $this->assertNotEmpty($updated['f_modificacion']);
        $edit->assertRedirectTo('contacto/' . $created['id']);

        $subpanel = $this->withSession($this->sessionFor(2, 2, 10))->post('contacto/addSubpanel', $this->withCsrf([
            'parent_type' => 'cpotencial',
            'parent_id' => 20,
            'nombre' => 'Contacto Subpanel',
            'telefono' => '5511112222',
            'celular' => '5533334444',
            'correo' => 'subpanel@example.com',
        ]));
        $subpanel->assertOK();
        $subpanel->assertJSONFragment(['exito' => true]);
        $this->assertSame(1, (int) db_connect()->table('contacto')->where('cpotencial_id', 20)->where('nombre', 'Contacto Subpanel')->countAllResults());

        $delete = $this->withSession($this->sessionFor(2, 2, 10))->post('contacto/delete/' . $created['id'], $this->withCsrf([]));
        $delete->assertJSONFragment(['exito' => true]);
        $deleted = db_connect()->table('contacto')->where('id', $created['id'])->get()->getRowArray();
        $this->assertSame(1, (int) $deleted['deleted']);
    }

    public function testContactRejectsOutOfScopeParentAndCsrfBeforeDataChanges(): void
    {
        $outOfScope = $this->withSession($this->sessionFor(2, 2, 10))->post('contacto/addSubpanel', $this->withCsrf([
            'parent_type' => 'cliente',
            'parent_id' => 11,
            'nombre' => 'Contacto No Permitido',
            'telefono' => '5511113333',
            'correo' => 'no@example.com',
        ]));
        $outOfScope->assertStatus(422);
        $this->assertSame(0, (int) db_connect()->table('contacto')->where('nombre', 'Contacto No Permitido')->countAllResults());

        $deleteOutOfScope = $this->withSession($this->sessionFor(2, 2, 10))->post('contacto/delete/101', $this->withCsrf([]));
        $deleteOutOfScope->assertStatus(404);
        $this->assertSame(0, (int) db_connect()->table('contacto')->select('deleted')->where('id', 101)->get()->getRowArray()['deleted']);

        try {
            $this->withSession($this->sessionFor(2, 2, 10))->post('contacto/add', [
                'cliente_id' => '10_1',
                'nombre' => 'Sin CSRF',
                'telefono' => '5511114444',
                'correo' => 'csrf@example.com',
            ]);
            $this->fail('Expected CSRF validation to reject the mutation.');
        } catch (SecurityException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(0, (int) db_connect()->table('contacto')->where('nombre', 'Sin CSRF')->countAllResults());
    }

    public function testCustomerAndProspectFormsRenderContactSubpanels(): void
    {
        $customer = $this->withSession($this->sessionFor(1, 1, 10))->get('cliente/10');
        $customer->assertOK();
        $customer->assertSee('Contactos');
        $customer->assertSee('Contacto Cliente Norte');

        $prospect = $this->withSession($this->sessionFor(4, 5, 10))->get('cpotencial/20');
        $prospect->assertOK();
        $prospect->assertSee('Contactos');
        $prospect->assertSee('Contacto Prospecto Norte');
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

    /** @param array<string, mixed> $params @return array<string, mixed> */
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
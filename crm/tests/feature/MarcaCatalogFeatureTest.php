<?php

use App\Services\AuthorizationService;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class MarcaCatalogFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['marca', 'usuario_ucomercial', 'usuario', 'ucomercial', 'perfil'] as $table) {
            $db->query('DROP TABLE IF EXISTS ' . $db->escapeIdentifiers($db->prefixTable($table)));
        }

        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('perfil')) . ' (
            id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('ucomercial')) . ' (
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
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('marca')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            deleted INTEGER DEFAULT 0,
            u_crea INTEGER NULL,
            f_creacion TEXT NULL,
            u_modifica INTEGER NULL,
            f_modificacion TEXT NULL
        )');

        foreach ([1, 2, 3, 4, 5, 6] as $profileId) {
            $db->table('perfil')->insert(['id' => $profileId, 'nombre' => 'Perfil ' . $profileId, 'deleted' => 0]);
        }
        $db->table('ucomercial')->insert(['id' => 10, 'nombre' => 'Unidad', 'deleted' => 0]);
        $db->table('usuario')->insert([
            'id' => 1,
            'nombre' => 'Admin',
            'usuario' => 'admin',
            'correo' => 'admin@example.com',
            'contrasenia' => password_hash('secret123', PASSWORD_DEFAULT),
            'perfil_id' => 1,
            'cgestion_id' => 1,
            'deleted' => 0,
        ]);
        $db->table('usuario_ucomercial')->insert(['usuario_id' => 1, 'ucomercial_id' => 10, 'deleted' => 0]);
        $db->table('marca')->insertBatch([
            ['id' => 1, 'nombre' => 'Marca Activa', 'deleted' => 0, 'u_crea' => 1],
            ['id' => 2, 'nombre' => 'Marca Eliminada', 'deleted' => 1, 'u_crea' => 1],
        ]);
    }

    public function testPilotCatalogListRendersInsideSharedShellWithActiveRowsOnly(): void
    {
        $response = $this->withSession($this->sessionFor(1))->get('marca');

        $response->assertOK();
        $response->assertSee('Marcas');
        $response->assertSee('Marca Activa');
        $response->assertDontSee('Marca Eliminada');
        $response->assertSee('main-content');
    }

    public function testPilotCatalogRowsEndpointKeepsLegacyDataTableShape(): void
    {
        $response = $this->withSession($this->sessionFor(1))->post('marca/get_rows', $this->withCsrf([
            'draw' => '3',
            'search' => ['value' => 'Activa'],
        ]));

        $response->assertOK();
        $response->assertJSONFragment([
            'draw' => 3,
            'recordsTotal' => 1,
            'recordsFiltered' => 1,
        ]);
        $payload = json_decode((string) $response->getJSON(), true);
        $this->assertSame('Marca Activa', $payload['data'][0]['nombre']);
        $this->assertSame(['edit', 'delete'], array_column($payload['data'][0]['_actions'], 'name'));
        $this->assertStringNotContainsString('<a', json_encode($payload['data'][0]['_actions']));
        $this->assertStringNotContainsString('<button', json_encode($payload['data'][0]['_actions']));
    }

    public function testPilotCatalogCreateEditAndSoftDeletePersistAuditFields(): void
    {
        $create = $this->withSession($this->sessionFor(1))->post('marca/add', $this->withCsrf([
            'nombre' => ' Nueva Marca ',
        ]));

        $created = db_connect()->table('marca')->where('nombre', 'Nueva Marca')->get()->getRowArray();
        $this->assertNotNull($created);
        $this->assertSame(1, (int) $created['u_crea']);
        $this->assertNotEmpty($created['f_creacion']);
        $create->assertRedirectTo('marca/' . $created['id']);

        $edit = $this->withSession($this->sessionFor(1))->post('marca/' . $created['id'], $this->withCsrf([
            'nombre' => 'Marca Actualizada',
        ]));

        $updated = db_connect()->table('marca')->where('id', $created['id'])->get()->getRowArray();
        $this->assertSame('Marca Actualizada', $updated['nombre']);
        $this->assertSame(1, (int) $updated['u_modifica']);
        $this->assertNotEmpty($updated['f_modificacion']);
        $edit->assertRedirectTo('marca/' . $created['id']);

        $delete = $this->withSession($this->sessionFor(1))->post('marca/delete/' . $created['id'], $this->withCsrf([]));

        $deleted = db_connect()->table('marca')->where('id', $created['id'])->get()->getRowArray();
        $this->assertSame(1, (int) $deleted['deleted']);
        $this->assertSame(1, (int) $deleted['u_modifica']);
        $this->assertNotEmpty($deleted['f_modificacion']);
        $delete->assertJSONFragment(['exito' => true]);
    }

    public function testPilotCatalogRejectsInvalidAndDuplicateInput(): void
    {
        $invalid = $this->withSession($this->sessionFor(1))->post('marca/add', $this->withCsrf([
            'nombre' => 'A',
        ]));
        $invalid->assertOK();
        $this->assertSame(0, (int) db_connect()->table('marca')->where('nombre', 'A')->countAllResults());

        $duplicateCreate = $this->withSession($this->sessionFor(1))->post('marca/add', $this->withCsrf([
            'nombre' => 'Marca Activa',
        ]));
        $duplicateCreate->assertOK();
        $this->assertSame(1, (int) db_connect()->table('marca')->where('nombre', 'Marca Activa')->where('deleted', 0)->countAllResults());

        db_connect()->table('marca')->insert(['id' => 3, 'nombre' => 'Marca Secundaria', 'deleted' => 0, 'u_crea' => 1]);
        $duplicateEdit = $this->withSession($this->sessionFor(1))->post('marca/3', $this->withCsrf([
            'nombre' => 'Marca Activa',
        ]));
        $duplicateEdit->assertOK();
        $stored = db_connect()->table('marca')->where('id', 3)->get()->getRowArray();
        $this->assertSame('Marca Secundaria', $stored['nombre']);
        $this->assertNull($stored['u_modifica']);
    }

    public function testPilotCatalogRejectsUnauthorizedMutationsBeforeDataChanges(): void
    {
        $unauthorized = $this->withSession($this->sessionFor(3))->get('marca');
        $unauthorized->assertStatus(403);

        $create = $this->withSession($this->sessionFor(3))->post('marca/add', $this->withCsrf([
            'nombre' => 'No autorizada',
        ]));
        $create->assertStatus(403);
        $this->assertSame(0, (int) db_connect()->table('marca')->where('nombre', 'No autorizada')->countAllResults());

        $edit = $this->withSession($this->sessionFor(3))->post('marca/1', $this->withCsrf([
            'nombre' => 'Cambio no autorizado',
        ]));
        $edit->assertStatus(403);
        $this->assertSame('Marca Activa', db_connect()->table('marca')->where('id', 1)->get()->getRowArray()['nombre']);

        $delete = $this->withSession($this->sessionFor(3))->post('marca/delete/1', $this->withCsrf([]));
        $delete->assertStatus(403);
        $this->assertSame(0, (int) db_connect()->table('marca')->where('id', 1)->get()->getRowArray()['deleted']);
    }

    public function testPilotCatalogCsrfRejectsMutationBeforeDataChanges(): void
    {
        try {
            $this->withSession($this->sessionFor(1))->post('marca/add', ['nombre' => 'Sin Token']);
            $this->fail('Expected CSRF validation to reject the mutation.');
        } catch (SecurityException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, (int) db_connect()->table('marca')->where('nombre', 'Sin Token')->countAllResults());
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
    private function sessionFor(int $profileId): array
    {
        return [
            'user' => [
                'user_id' => 1,
                'nombre' => 'Admin',
                'correo' => 'admin@example.com',
                'perfil_id' => $profileId,
                'cgestion_id' => 1,
                'ucomercial_id' => 10,
                'ucomercial_ids' => [10],
            ],
            'permissions' => (new AuthorizationService())->permissionsForProfile($profileId),
        ];
    }
}

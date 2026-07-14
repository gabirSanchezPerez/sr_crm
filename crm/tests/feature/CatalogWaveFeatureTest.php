<?php

use App\Services\AuthorizationService;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class CatalogWaveFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['estado', 'sector', 'ucomercial', 'cgestion', 'perfil', 'usuario_ucomercial', 'usuario'] as $table) {
            $db->query('DROP TABLE IF EXISTS ' . $db->escapeIdentifiers($db->prefixTable($table)));
        }

        foreach (['estado', 'sector', 'ucomercial', 'cgestion', 'perfil'] as $table) {
            $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable($table)) . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                nombre TEXT NOT NULL,
                deleted INTEGER DEFAULT 0,
                u_crea INTEGER NULL,
                f_creacion TEXT NULL,
                u_modifica INTEGER NULL,
                f_modificacion TEXT NULL
            )');
        }

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

        foreach ([1, 2, 3, 4, 5, 6] as $profileId) {
            $db->table('perfil')->insert(['id' => $profileId, 'nombre' => 'Perfil ' . $profileId, 'deleted' => 0]);
        }

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
        $db->table('usuario_ucomercial')->insert(['usuario_id' => 1, 'ucomercial_id' => 1, 'deleted' => 0]);

        foreach (['estado', 'sector', 'ucomercial', 'cgestion'] as $table) {
            $db->table($table)->insertBatch([
                ['id' => 1, 'nombre' => ucfirst($table) . ' activo', 'deleted' => 0, 'u_crea' => 1],
                ['id' => 2, 'nombre' => ucfirst($table) . ' eliminado', 'deleted' => 1, 'u_crea' => 1],
            ]);
        }
    }

    /**
     * @dataProvider catalogProvider
     */
    public function testCatalogWaveRendersActiveRowsAndDataTableShape(string $route, string $label, string $pluralLabel, string $activeName, bool $supportsDelete): void
    {
        $response = $this->withSession($this->sessionFor(1))->get($route);

        $response->assertOK();
        $response->assertSee($pluralLabel);
        $response->assertSee($activeName);
        $response->assertDontSee(ucfirst($route) . ' eliminado');

        $rows = $this->withSession($this->sessionFor(1))->post($route . '/get_rows', $this->withCsrf([
            'draw' => '4',
            'search' => ['value' => 'activo'],
        ]));

        $rows->assertOK();
        $rows->assertJSONFragment(['draw' => 4, 'recordsTotal' => 1, 'recordsFiltered' => 1]);
        $payload = json_decode((string) $rows->getJSON(), true);
        $actionNames = array_column($payload['data'][0]['_actions'], 'name');
        $this->assertContains('edit', $actionNames);

        if ($supportsDelete) {
            $this->assertContains('delete', $actionNames);
        } else {
            $this->assertNotContains('delete', $actionNames);
            try {
                $this->withSession($this->sessionFor(1))->post($route . '/delete/1', $this->withCsrf([]));
                $this->fail('Expected delete route to remain unavailable for ' . $route . '.');
            } catch (PageNotFoundException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @dataProvider catalogProvider
     */
    public function testCatalogWaveCreatesEditsAndDeletesWhereAccepted(string $route, string $label, string $pluralLabel, string $activeName, bool $supportsDelete): void
    {
        $create = $this->withSession($this->sessionFor(1))->post($route . '/add', $this->withCsrf([
            'nombre' => ' Nuevo ' . $label . ' ',
        ]));

        $created = db_connect()->table($route)->where('nombre', 'Nuevo ' . $label)->get()->getRowArray();
        $this->assertNotNull($created);
        $this->assertSame(1, (int) $created['u_crea']);
        $this->assertNotEmpty($created['f_creacion']);
        $create->assertRedirectTo($route . '/' . $created['id']);

        $edit = $this->withSession($this->sessionFor(1))->post($route . '/' . $created['id'], $this->withCsrf([
            'nombre' => $label . ' actualizado',
        ]));

        $updated = db_connect()->table($route)->where('id', $created['id'])->get()->getRowArray();
        $this->assertSame($label . ' actualizado', $updated['nombre']);
        $this->assertSame(1, (int) $updated['u_modifica']);
        $this->assertNotEmpty($updated['f_modificacion']);
        $edit->assertRedirectTo($route . '/' . $created['id']);

        if ($supportsDelete) {
            $delete = $this->withSession($this->sessionFor(1))->post($route . '/delete/' . $created['id'], $this->withCsrf([]));
            $deleted = db_connect()->table($route)->where('id', $created['id'])->get()->getRowArray();
            $this->assertSame(1, (int) $deleted['deleted']);
            $this->assertSame(1, (int) $deleted['u_modifica']);
            $this->assertNotEmpty($deleted['f_modificacion']);
            $delete->assertJSONFragment(['exito' => true]);
        }
    }

    /**
     * @dataProvider catalogProvider
     */
    public function testCatalogWaveRejectsInvalidAndDuplicateCatalogData(string $route, string $label, string $pluralLabel, string $activeName, bool $supportsDelete): void
    {
        $invalid = $this->withSession($this->sessionFor(1))->post($route . '/add', $this->withCsrf([
            'nombre' => 'A',
        ]));
        $invalid->assertOK();
        $this->assertSame(0, (int) db_connect()->table($route)->where('nombre', 'A')->countAllResults());

        $duplicateCreate = $this->withSession($this->sessionFor(1))->post($route . '/add', $this->withCsrf([
            'nombre' => ucfirst($route) . ' activo',
        ]));
        $duplicateCreate->assertOK();
        $this->assertSame(1, (int) db_connect()->table($route)->where('nombre', ucfirst($route) . ' activo')->where('deleted', 0)->countAllResults());

        db_connect()->table($route)->insert(['id' => 3, 'nombre' => $label . ' alterno', 'deleted' => 0, 'u_crea' => 1]);
        $duplicateEdit = $this->withSession($this->sessionFor(1))->post($route . '/3', $this->withCsrf([
            'nombre' => ucfirst($route) . ' activo',
        ]));
        $duplicateEdit->assertOK();
        $stored = db_connect()->table($route)->where('id', 3)->get()->getRowArray();
        $this->assertSame($label . ' alterno', $stored['nombre']);
        $this->assertNull($stored['u_modifica']);
    }

    public function testCatalogWaveRejectsUnauthorizedMutationsBeforeDataChanges(): void
    {
        $create = $this->withSession($this->sessionFor(3))->post('ucomercial/add', $this->withCsrf([
            'nombre' => 'Unidad no autorizada',
        ]));
        $create->assertStatus(403);
        $this->assertSame(0, (int) db_connect()->table('ucomercial')->where('nombre', 'Unidad no autorizada')->countAllResults());

        $edit = $this->withSession($this->sessionFor(3))->post('ucomercial/1', $this->withCsrf([
            'nombre' => 'Cambio no autorizado',
        ]));
        $edit->assertStatus(403);
        $this->assertSame('Ucomercial activo', db_connect()->table('ucomercial')->where('id', 1)->get()->getRowArray()['nombre']);

        $delete = $this->withSession($this->sessionFor(3))->post('ucomercial/delete/1', $this->withCsrf([]));
        $delete->assertStatus(403);
        $this->assertSame(0, (int) db_connect()->table('ucomercial')->where('id', 1)->get()->getRowArray()['deleted']);
    }

    public function testCatalogWaveDeniesUnauthorizedCatalogAccess(): void
    {
        $this->withSession($this->sessionFor(3))->get('estado')->assertStatus(403);
        $this->withSession($this->sessionFor(3))->get('ucomercial')->assertStatus(403);
        $this->withSession($this->sessionFor(5))->get('ucomercial')->assertOK();
    }

    /**
     * @return iterable<string, array{string, string, string, string, bool}>
     */
    public static function catalogProvider(): iterable
    {
        yield 'estado' => ['estado', 'Estado', 'Estados', 'Estado activo', false];
        yield 'sector' => ['sector', 'Sector', 'Sectores', 'Sector activo', false];
        yield 'ucomercial' => ['ucomercial', 'Unidad comercial', 'Unidades comerciales', 'Ucomercial activo', true];
        yield 'cgestion' => ['cgestion', 'Canal de gestion', 'Canales de gestion', 'Cgestion activo', true];
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
                'ucomercial_id' => 1,
                'ucomercial_ids' => [1],
            ],
            'permissions' => (new AuthorizationService())->permissionsForProfile($profileId),
        ];
    }
}

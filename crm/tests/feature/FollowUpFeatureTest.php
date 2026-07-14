<?php

use App\Services\AuthorizationService;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class FollowUpFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['seguimiento', 'cpotencial', 'cliente', 'usuario_ucomercial', 'usuario', 'actividad', 'estado', 'sector', 'ucomercial', 'cgestion', 'perfil'] as $table) {
            $db->query('DROP TABLE IF EXISTS ' . $db->escapeIdentifiers($db->prefixTable($table)));
        }

        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('perfil')) . ' (id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0)');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cgestion')) . ' (id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0)');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('ucomercial')) . ' (id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0)');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('sector')) . ' (id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0)');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('actividad')) . ' (id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, activo INTEGER DEFAULT 1, deleted INTEGER DEFAULT 0)');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('estado')) . ' (id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0)');
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
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('seguimiento')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            fecha TEXT NOT NULL,
            hora TEXT NOT NULL,
            actividad_id INTEGER NOT NULL,
            descripcion TEXT NULL,
            adjunto TEXT NULL,
            estado_id INTEGER NOT NULL,
            cliente_id INTEGER NOT NULL,
            ejecutivo_id INTEGER NOT NULL,
            tipo_id INTEGER NOT NULL,
            monto REAL NULL,
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
        $db->table('actividad')->insertBatch([
            ['id' => 1, 'nombre' => 'Llamada', 'activo' => 1, 'deleted' => 0],
            ['id' => 2, 'nombre' => 'Reunion eliminada', 'activo' => 1, 'deleted' => 1],
        ]);
        $db->table('estado')->insertBatch([
            ['id' => 1, 'nombre' => 'Pendiente', 'deleted' => 0],
            ['id' => 2, 'nombre' => 'Cerrado', 'deleted' => 0],
            ['id' => 3, 'nombre' => 'Estado eliminado', 'deleted' => 1],
        ]);

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
        $db->table('seguimiento')->insertBatch([
            ['id' => 100, 'fecha' => '2026-07-10', 'hora' => '09:00:00', 'actividad_id' => 1, 'descripcion' => 'Llamada cliente norte', 'estado_id' => 1, 'cliente_id' => 10, 'ejecutivo_id' => 3, 'tipo_id' => 1, 'monto' => 100, 'u_crea' => 1, 'deleted' => 0],
            ['id' => 101, 'fecha' => '2026-07-11', 'hora' => '10:00:00', 'actividad_id' => 1, 'descripcion' => 'Llamada cliente sur', 'estado_id' => 1, 'cliente_id' => 11, 'ejecutivo_id' => 4, 'tipo_id' => 1, 'monto' => 200, 'u_crea' => 1, 'deleted' => 0],
            ['id' => 102, 'fecha' => '2026-07-12', 'hora' => '11:00:00', 'actividad_id' => 1, 'descripcion' => 'Llamada prospecto norte', 'estado_id' => 1, 'cliente_id' => 20, 'ejecutivo_id' => 3, 'tipo_id' => 2, 'monto' => 300, 'u_crea' => 1, 'deleted' => 0],
            ['id' => 103, 'fecha' => '2026-07-13', 'hora' => '12:00:00', 'actividad_id' => 1, 'descripcion' => 'Llamada prospecto convertido', 'estado_id' => 1, 'cliente_id' => 22, 'ejecutivo_id' => 3, 'tipo_id' => 2, 'monto' => 400, 'u_crea' => 1, 'deleted' => 0],
            ['id' => 104, 'fecha' => '2026-07-14', 'hora' => '13:00:00', 'actividad_id' => 1, 'descripcion' => 'Llamada padre eliminado', 'estado_id' => 1, 'cliente_id' => 12, 'ejecutivo_id' => 3, 'tipo_id' => 1, 'monto' => 500, 'u_crea' => 1, 'deleted' => 0],
            ['id' => 105, 'fecha' => '2026-07-15', 'hora' => '14:00:00', 'actividad_id' => 1, 'descripcion' => 'Llamada seguimiento eliminado', 'estado_id' => 1, 'cliente_id' => 10, 'ejecutivo_id' => 3, 'tipo_id' => 1, 'monto' => 600, 'u_crea' => 1, 'deleted' => 1],
        ]);
    }

    public function testFollowUpRowsRespectScopeAndInactiveParents(): void
    {
        $team = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/get_rows', $this->withCsrf([
            'draw' => '9',
            'search' => ['value' => 'Llamada'],
        ]));

        $team->assertOK();
        $payload = json_decode((string) $team->getJSON(), true);
        $descriptions = array_column($payload['data'], 'descripcion');
        $this->assertSame(2, $payload['recordsTotal']);
        $this->assertSame(['Llamada prospecto norte', 'Llamada cliente norte'], $descriptions);
        $this->assertNotContains('Llamada cliente sur', $descriptions);
        $this->assertNotContains('Llamada prospecto convertido', $descriptions);
        $this->assertNotContains('Llamada padre eliminado', $descriptions);
        $this->assertNotContains('Llamada seguimiento eliminado', $descriptions);
        $this->assertSame(['edit', 'delete'], array_column($payload['data'][0]['_actions'], 'name'));
    }

    public function testFollowUpCreateEditDeleteAndParentSelectorPersistAuditFields(): void
    {
        $selector = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/selectRowForType', $this->withCsrf([
            'searchTerm' => 'Norte',
        ]));
        $selector->assertOK();
        $options = json_decode((string) $selector->getJSON(), true);
        $ids = array_column($options, 'id');
        $this->assertContains('10_1', $ids);
        $this->assertContains('20_2', $ids);
        $this->assertNotContains('11_1', $ids);
        $this->assertNotContains('22_2', $ids);

        $create = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/add', $this->withCsrf([
            'cliente_id' => '20_2',
            'fecha' => '2026-07-16',
            'hora' => '15:30',
            'actividad_id' => 1,
            'estado_id' => 1,
            'ejecutivo_id' => 3,
            'descripcion' => ' Seguimiento nuevo ',
            'monto' => '123.45',
        ]));

        $create->assertRedirect();
        $created = db_connect()->table('seguimiento')->where('descripcion', 'Seguimiento nuevo')->get()->getRowArray();
        $this->assertNotNull($created);
        $this->assertSame(20, (int) $created['cliente_id']);
        $this->assertSame(2, (int) $created['tipo_id']);
        $this->assertSame(2, (int) $created['u_crea']);
        $this->assertNotEmpty($created['f_creacion']);
        $this->assertSame(123.45, (float) $created['monto']);
        $create->assertRedirectTo('seguimiento/' . $created['id']);

        $edit = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/' . $created['id'], $this->withCsrf([
            'fecha' => '2026-07-17',
            'hora' => '16:45:00',
            'actividad_id' => 1,
            'estado_id' => 2,
            'ejecutivo_id' => 3,
            'descripcion' => 'Seguimiento actualizado',
            'monto' => '456.00',
        ]));
        $edit->assertRedirectTo('seguimiento/' . $created['id']);
        $updated = db_connect()->table('seguimiento')->where('id', $created['id'])->get()->getRowArray();
        $this->assertSame('Seguimiento actualizado', $updated['descripcion']);
        $this->assertSame(20, (int) $updated['cliente_id']);
        $this->assertSame(2, (int) $updated['tipo_id']);
        $this->assertSame(2, (int) $updated['estado_id']);
        $this->assertSame(2, (int) $updated['u_modifica']);
        $this->assertNotEmpty($updated['f_modificacion']);

        $delete = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/delete/' . $created['id'], $this->withCsrf([]));
        $delete->assertJSONFragment(['exito' => true]);
        $deleted = db_connect()->table('seguimiento')->where('id', $created['id'])->get()->getRowArray();
        $this->assertSame(1, (int) $deleted['deleted']);
        $this->assertSame(2, (int) $deleted['u_modifica']);
    }

    public function testFollowUpRejectsInvalidReferencesAndCrossScopeMutations(): void
    {
        $outOfScope = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/add', $this->withCsrf([
            'cliente_id' => '11_1',
            'fecha' => '2026-07-18',
            'hora' => '09:30',
            'actividad_id' => 1,
            'estado_id' => 1,
            'ejecutivo_id' => 3,
            'descripcion' => 'Seguimiento no permitido',
        ]));
        $outOfScope->assertOK();
        $this->assertSame(0, (int) db_connect()->table('seguimiento')->where('descripcion', 'Seguimiento no permitido')->countAllResults());

        $deletedActivity = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/add', $this->withCsrf([
            'cliente_id' => '10_1',
            'fecha' => '2026-07-18',
            'hora' => '10:30',
            'actividad_id' => 2,
            'estado_id' => 1,
            'ejecutivo_id' => 3,
            'descripcion' => 'Seguimiento actividad eliminada',
        ]));
        $deletedActivity->assertOK();
        $this->assertSame(0, (int) db_connect()->table('seguimiento')->where('descripcion', 'Seguimiento actividad eliminada')->countAllResults());

        $deletedState = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/100', $this->withCsrf([
            'fecha' => '2026-07-18',
            'hora' => '11:30',
            'actividad_id' => 1,
            'estado_id' => 3,
            'ejecutivo_id' => 3,
            'descripcion' => 'Seguimiento estado eliminado',
        ]));
        $deletedState->assertOK();
        $unchanged = db_connect()->table('seguimiento')->where('id', 100)->get()->getRowArray();
        $this->assertSame('Llamada cliente norte', $unchanged['descripcion']);
        $this->assertSame(1, (int) $unchanged['estado_id']);

        $editOutOfScope = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/101', $this->withCsrf([
            'fecha' => '2026-07-19',
            'hora' => '12:00',
            'actividad_id' => 1,
            'estado_id' => 1,
            'ejecutivo_id' => 4,
            'descripcion' => 'Cambio fuera de alcance',
        ]));
        $editOutOfScope->assertStatus(404);
        $this->assertSame('Llamada cliente sur', db_connect()->table('seguimiento')->select('descripcion')->where('id', 101)->get()->getRowArray()['descripcion']);

        $deleteOutOfScope = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/delete/101', $this->withCsrf([]));
        $deleteOutOfScope->assertStatus(404);
        $this->assertSame(0, (int) db_connect()->table('seguimiento')->select('deleted')->where('id', 101)->get()->getRowArray()['deleted']);
    }

    public function testFollowUpCsrfRejectsMutationBeforeDataChanges(): void
    {
        try {
            $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/add', [
                'cliente_id' => '10_1',
                'fecha' => '2026-07-20',
                'hora' => '09:00',
                'actividad_id' => 1,
                'estado_id' => 1,
                'ejecutivo_id' => 3,
                'descripcion' => 'Seguimiento sin CSRF',
            ]);
            $this->fail('Expected CSRF validation to reject the mutation.');
        } catch (SecurityException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, (int) db_connect()->table('seguimiento')->where('descripcion', 'Seguimiento sin CSRF')->countAllResults());
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
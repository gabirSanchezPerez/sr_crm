<?php

use App\Services\AuthorizationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class AccountTransferFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['seguimiento', 'cpotencial', 'cliente', 'usuario_ucomercial', 'usuario', 'sector', 'ucomercial', 'cgestion', 'perfil'] as $table) {
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
            u_crea INTEGER NULL, u_modifica INTEGER NULL, f_creacion TEXT NULL, f_modificacion TEXT NULL,
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
            tipo_id INTEGER NOT NULL,
            cliente_id INTEGER NOT NULL,
            ejecutivo_id INTEGER NOT NULL,
            u_modifica INTEGER NULL,
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
        $this->insertUser(3, 'Ejecutivo Origen', 3, 10);
        $this->insertUser(4, 'Ejecutivo Destino', 3, 10);
        $this->insertUser(5, 'Ejecutivo Sur', 3, 20);

        $db->table('cliente')->insertBatch([
            ['id' => 10, 'razon_social' => 'Cliente Transferible', 'marca' => 'Marca A', 'cgestion_id' => 1, 'ejecutivo_id' => 3, 'deleted' => 0],
            ['id' => 11, 'razon_social' => 'Cliente Ajeno', 'marca' => 'Marca B', 'cgestion_id' => 1, 'ejecutivo_id' => 5, 'deleted' => 0],
        ]);
        $db->table('cpotencial')->insertBatch([
            ['id' => 20, 'razon_social' => 'Prospecto Transferible', 'marca' => 'Marca CP', 'rfc' => 'PTR010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 3, 'cliente_id' => null, 'deleted' => 0],
            ['id' => 21, 'razon_social' => 'Prospecto Ajeno', 'marca' => 'Marca Sur', 'rfc' => 'SUR010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 5, 'cliente_id' => null, 'deleted' => 0],
        ]);
        $db->table('seguimiento')->insertBatch([
            ['id' => 100, 'tipo_id' => 1, 'cliente_id' => 10, 'ejecutivo_id' => 3, 'deleted' => 0],
            ['id' => 101, 'tipo_id' => 2, 'cliente_id' => 20, 'ejecutivo_id' => 3, 'deleted' => 0],
            ['id' => 102, 'tipo_id' => 1, 'cliente_id' => 11, 'ejecutivo_id' => 5, 'deleted' => 0],
        ]);
    }

    public function testTransferAccountsMovesOwnedCustomerProspectAndFollowUpsWithResultSummary(): void
    {
        $response = $this->withSession($this->sessionFor(1, 1, 10))->post('auth/transfering', $this->withCsrf([
            'fromUser' => 3,
            'newEjecutivo' => 4,
            'accounts' => [
                'A' => [10, 11],
                'CP' => [20],
            ],
        ]));

        $response->assertOK();
        $response->assertJSONFragment(['exito' => true, 'transferred' => 2, 'skipped' => 1]);
        $payload = json_decode((string) $response->getJSON(), true);
        $this->assertSame([
            ['type' => 'A', 'id' => 10, 'before' => 3, 'after' => 4, 'status' => 'transferred'],
            ['type' => 'A', 'id' => 11, 'before' => 5, 'after' => 4, 'status' => 'skipped'],
            ['type' => 'CP', 'id' => 20, 'before' => 3, 'after' => 4, 'status' => 'transferred'],
        ], $payload['results']);

        $db = db_connect();
        $client = $db->table('cliente')->where('id', 10)->get()->getRowArray();
        $this->assertSame(4, (int) $client['ejecutivo_id']);
        $this->assertSame(1, (int) $client['u_modifica']);
        $this->assertNotEmpty($client['f_modificacion']);

        $skippedClient = $db->table('cliente')->where('id', 11)->get()->getRowArray();
        $this->assertSame(5, (int) $skippedClient['ejecutivo_id']);
        $this->assertNull($skippedClient['u_modifica']);

        $prospect = $db->table('cpotencial')->where('id', 20)->get()->getRowArray();
        $this->assertSame(4, (int) $prospect['ejecutivo_id']);
        $this->assertSame(1, (int) $prospect['u_modifica']);
        $this->assertNotEmpty($prospect['f_modificacion']);

        $clientFollowUp = $db->table('seguimiento')->where('id', 100)->get()->getRowArray();
        $prospectFollowUp = $db->table('seguimiento')->where('id', 101)->get()->getRowArray();
        $unmovedFollowUp = $db->table('seguimiento')->where('id', 102)->get()->getRowArray();
        $this->assertSame(4, (int) $clientFollowUp['ejecutivo_id']);
        $this->assertSame(4, (int) $prospectFollowUp['ejecutivo_id']);
        $this->assertSame(5, (int) $unmovedFollowUp['ejecutivo_id']);
    }

    public function testTransferRejectsInvalidRequestsBeforeDataChanges(): void
    {
        $sameUser = $this->withSession($this->sessionFor(1, 1, 10))->post('auth/transfering', $this->withCsrf([
            'fromUser' => 3,
            'newEjecutivo' => 3,
            'accounts' => ['A' => [10]],
        ]));
        $sameUser->assertStatus(422);

        $noAccounts = $this->withSession($this->sessionFor(1, 1, 10))->post('auth/transfering', $this->withCsrf([
            'fromUser' => 3,
            'newEjecutivo' => 4,
            'accounts' => [],
        ]));
        $noAccounts->assertStatus(422);

        $unauthorized = $this->withSession($this->sessionFor(3, 3, 10))->post('auth/transfering', $this->withCsrf([
            'fromUser' => 3,
            'newEjecutivo' => 4,
            'accounts' => ['A' => [10]],
        ]));
        $unauthorized->assertStatus(403);

        $db = db_connect();
        $this->assertSame(3, (int) $db->table('cliente')->select('ejecutivo_id')->where('id', 10)->get()->getRowArray()['ejecutivo_id']);
        $this->assertSame(3, (int) $db->table('cpotencial')->select('ejecutivo_id')->where('id', 20)->get()->getRowArray()['ejecutivo_id']);
        $this->assertSame(3, (int) $db->table('seguimiento')->select('ejecutivo_id')->where('id', 100)->get()->getRowArray()['ejecutivo_id']);
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
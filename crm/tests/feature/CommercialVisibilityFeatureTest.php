<?php

use App\Services\AuthorizationService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

final class CommercialVisibilityFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['cpotencial', 'cliente', 'usuario_ucomercial', 'usuario', 'sector', 'ucomercial', 'cgestion', 'perfil'] as $table) {
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

        foreach ([1, 2, 3, 4, 5, 6] as $profileId) {
            $db->table('perfil')->insert(['id' => $profileId, 'nombre' => 'Perfil ' . $profileId, 'deleted' => 0]);
        }
        $db->table('cgestion')->insert(['id' => 1, 'nombre' => 'Gestion', 'deleted' => 0]);
        $db->table('ucomercial')->insertBatch([
            ['id' => 10, 'nombre' => 'Unidad Norte', 'deleted' => 0],
            ['id' => 20, 'nombre' => 'Unidad Sur', 'deleted' => 0],
        ]);
        $db->table('sector')->insert(['id' => 1, 'nombre' => 'Retail', 'deleted' => 0]);

        $this->insertUser(1, 'Perfil 1 Admin', 1, 10);
        $this->insertUser(2, 'Perfil 2 Team', 2, 10);
        $this->insertUser(3, 'Perfil 3 Owner', 3, 10);
        $this->insertUser(4, 'Perfil 4 Broad', 4, 10);
        $this->insertUser(5, 'Perfil 5 Team', 5, 10);
        $this->insertUser(6, 'Perfil 6 Team', 6, 10);
        $this->insertUser(7, 'Ejecutivo Sur', 3, 20);

        $db->table('cliente')->insertBatch([
            ['id' => 10, 'razon_social' => 'Cliente Norte', 'marca' => 'Marca Norte', 'rfc' => 'CLN010101AAA', 'sector_id' => 1, 'cgestion_id' => 1, 'ejecutivo_id' => 3, 'deleted' => 0],
            ['id' => 11, 'razon_social' => 'Cliente Sur', 'marca' => 'Marca Sur', 'rfc' => 'CLS010101AAA', 'sector_id' => 1, 'cgestion_id' => 1, 'ejecutivo_id' => 7, 'deleted' => 0],
            ['id' => 12, 'razon_social' => 'Cliente Eliminado', 'marca' => 'Marca Baja', 'rfc' => 'CLE010101AAA', 'sector_id' => 1, 'cgestion_id' => 1, 'ejecutivo_id' => 3, 'deleted' => 1],
        ]);
        $db->table('cpotencial')->insertBatch([
            ['id' => 20, 'razon_social' => 'Prospecto Norte', 'marca' => 'Marca Norte', 'rfc' => 'PRN010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 3, 'cliente_id' => null, 'deleted' => 0],
            ['id' => 21, 'razon_social' => 'Prospecto Sur', 'marca' => 'Marca Sur', 'rfc' => 'PRS010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 7, 'cliente_id' => null, 'deleted' => 0],
            ['id' => 22, 'razon_social' => 'Prospecto Convertido', 'marca' => 'Marca Conv', 'rfc' => 'PRC010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 3, 'cliente_id' => 10, 'deleted' => 0],
            ['id' => 23, 'razon_social' => 'Prospecto Eliminado', 'marca' => 'Marca Baja', 'rfc' => 'PRE010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 3, 'cliente_id' => null, 'deleted' => 1],
        ]);
    }

    /**
     * @dataProvider profileVisibilityProvider
     */
    public function testCustomerAndProspectVisibilityForEveryAcceptedProfile(int $profileId, int $userId, bool $seesSouth): void
    {
        $customer = $this->withSession($this->sessionFor($profileId, $userId, 10))->get('cliente');
        $customer->assertOK();
        $customer->assertSee('Cliente Norte');
        $seesSouth ? $customer->assertSee('Cliente Sur') : $customer->assertDontSee('Cliente Sur');
        $customer->assertDontSee('Cliente Eliminado');

        $prospect = $this->withSession($this->sessionFor($profileId, $userId, 10))->get('cpotencial');
        $prospect->assertOK();
        $prospect->assertSee('Prospecto Norte');
        $seesSouth ? $prospect->assertSee('Prospecto Sur') : $prospect->assertDontSee('Prospecto Sur');
        $prospect->assertDontSee('Prospecto Convertido');
        $prospect->assertDontSee('Prospecto Eliminado');
    }

    /**
     * @return iterable<string, array{int,int,bool}>
     */
    public static function profileVisibilityProvider(): iterable
    {
        yield 'profile 1 all' => [1, 1, true];
        yield 'profile 2 team' => [2, 2, false];
        yield 'profile 3 owner' => [3, 3, false];
        yield 'profile 4 all' => [4, 4, true];
        yield 'profile 5 team' => [5, 5, false];
        yield 'profile 6 team' => [6, 6, false];
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
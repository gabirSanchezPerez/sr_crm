<?php

use App\Services\AuthorizationService;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class AccessControlFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['seguimiento', 'cpotencial', 'cliente', 'usuario_ucomercial', 'usuario', 'ucomercial', 'cgestion', 'perfil'] as $table) {
            $db->query('DROP TABLE IF EXISTS ' . $db->escapeIdentifiers($db->prefixTable($table)));
        }

        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('perfil')) . ' (
            id INTEGER PRIMARY KEY, nombre TEXT NOT NULL, deleted INTEGER DEFAULT 0,
            u_crea INTEGER NULL, f_creacion TEXT NULL, u_modifica INTEGER NULL, f_modificacion TEXT NULL
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cgestion')) . ' (
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
            deleted INTEGER DEFAULT 0, u_crea INTEGER NULL, f_creacion TEXT NULL, u_modifica INTEGER NULL, f_modificacion TEXT NULL
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cliente')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, razon_social TEXT NOT NULL, marca TEXT NOT NULL,
            ejecutivo_id INTEGER NOT NULL, deleted INTEGER DEFAULT 0, u_modifica INTEGER NULL, f_modificacion TEXT NULL
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cpotencial')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, razon_social TEXT NOT NULL, marca TEXT NOT NULL,
            ejecutivo_id INTEGER NOT NULL, cliente_id INTEGER NULL, deleted INTEGER DEFAULT 0,
            u_modifica INTEGER NULL, f_modificacion TEXT NULL
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('seguimiento')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, tipo_id INTEGER NOT NULL, cliente_id INTEGER NOT NULL,
            ejecutivo_id INTEGER NOT NULL, deleted INTEGER DEFAULT 0, u_modifica INTEGER NULL, f_modificacion TEXT NULL
        )');

        foreach ([1, 2, 3, 4, 5, 6] as $profileId) {
            $db->table('perfil')->insert(['id' => $profileId, 'nombre' => 'Perfil ' . $profileId, 'deleted' => 0]);
        }
        $db->table('cgestion')->insertBatch([
            ['id' => 1, 'nombre' => 'Gestion 1', 'deleted' => 0],
            ['id' => 2, 'nombre' => 'Gestion 2', 'deleted' => 0],
        ]);
        $db->table('ucomercial')->insertBatch([
            ['id' => 10, 'nombre' => 'Unidad Norte', 'deleted' => 0],
            ['id' => 20, 'nombre' => 'Unidad Sur', 'deleted' => 0],
        ]);

        $this->insertUser(1, 'Admin', 'admin', 'admin@example.com', 1, 10);
        $this->insertUser(2, 'Gerente Norte', 'manager', 'manager@example.com', 2, 10);
        $this->insertUser(3, 'Ejecutivo Norte', 'owner', 'owner@example.com', 3, 10);
        $this->insertUser(4, 'Ejecutivo Sur', 'outsider', 'outsider@example.com', 3, 20);
        $this->insertUser(5, 'Inactivo', 'inactive', 'inactive@example.com', 3, 10, true);
    }

    public function testValidLoginCreatesMinimalSessionAndRedirects(): void
    {
        $response = $this->post('login', $this->withCsrf([
            'identity' => 'admin@example.com',
            'password' => 'secret123',
        ]));

        $response->assertRedirectTo('home');
        $response->assertSessionHas('user');
        $response->assertSessionHas('permissions', (new AuthorizationService())->permissionsForProfile(1));
        $this->assertSame(1, $_SESSION['user']['user_id']);
        $this->assertSame(10, $_SESSION['user']['ucomercial_id']);
        $this->assertArrayNotHasKey('contrasenia', $_SESSION['user']);
    }

    public function testInvalidLoginAndInactiveUserDoNotCreateAuthenticatedSession(): void
    {
        $badPassword = $this->post('login', $this->withCsrf([
            'identity' => 'admin@example.com',
            'password' => 'wrong-password',
        ]));
        $badPassword->assertRedirect();
        $badPassword->assertSessionMissing('user');

        $inactive = $this->post('login', $this->withCsrf([
            'identity' => 'inactive@example.com',
            'password' => 'secret123',
        ]));
        $inactive->assertRedirect();
        $inactive->assertSessionMissing('user');
    }

    public function testProtectedHtmlRouteRedirectsAnonymousUserToLogin(): void
    {
        $response = $this->get('usuario');

        $response->assertRedirectTo('login');
        $response->assertSessionHas('requested_page');
    }

    public function testInvalidCsrfRejectsMutationBeforeDataChanges(): void
    {
        try {
            $this->withSession($this->sessionFor(1, 1, 10))
                ->post('usuario/add', [
                    'nombre' => 'Nuevo Usuario',
                    'usuario' => 'new.user',
                    'correo' => 'new@example.com',
                    'contrasenia' => 'secret123',
                    'perfil_id' => 3,
                    'cgestion_id' => 1,
                    'ucomercial_id' => [10],
                ]);
            $this->fail('Expected CSRF validation to reject the mutation.');
        } catch (SecurityException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, (int) db_connect()->table('usuario')->where('correo', 'new@example.com')->countAllResults());
    }

    public function testUnauthorizedProfileCannotAccessUserAdministrationDirectly(): void
    {
        $response = $this->withSession($this->sessionFor(3, 3, 10))->get('usuario');

        $response->assertStatus(403);
    }

    public function testTeamScopedUserCannotEditOutOfScopeRecord(): void
    {
        $sameTeam = $this->withSession($this->sessionFor(2, 2, 10))->get('profile/3');
        $sameTeam->assertOK();

        $outOfScope = $this->withSession($this->sessionFor(2, 2, 10))->get('profile/4');
        $outOfScope->assertStatus(403);
    }

    private function insertUser(int $id, string $name, string $username, string $email, int $profileId, int $unitId, bool $deleted = false): void
    {
        $db = db_connect();
        $db->table('usuario')->insert([
            'id' => $id,
            'nombre' => $name,
            'usuario' => $username,
            'correo' => $email,
            'contrasenia' => password_hash('secret123', PASSWORD_DEFAULT),
            'perfil_id' => $profileId,
            'cgestion_id' => 1,
            'deleted' => $deleted ? 1 : 0,
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

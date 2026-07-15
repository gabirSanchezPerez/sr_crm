<?php

use App\Services\AuthorizationService;
use App\Services\FollowUpService;
use App\Services\ProposalService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class ProposalFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        foreach (['seguimiento', 'documento', 'propuesta', 'contacto', 'cpotencial', 'cliente', 'usuario_ucomercial', 'usuario', 'actividad', 'estado', 'sector', 'ucomercial', 'cgestion', 'perfil'] as $table) {
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
            id INTEGER PRIMARY KEY AUTOINCREMENT, usuario_id INTEGER NOT NULL, ucomercial_id INTEGER NOT NULL, deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cliente')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, razon_social TEXT NOT NULL, marca TEXT NOT NULL, rfc TEXT NULL,
            sector_id INTEGER NULL, cpotencial_id INTEGER NULL, cgestion_id INTEGER NOT NULL, ejecutivo_id INTEGER NOT NULL,
            u_crea INTEGER NULL, u_modifica INTEGER NULL, f_creacion TEXT NULL, f_modificacion TEXT NULL,
            deleted INTEGER DEFAULT 0, _countries_id INTEGER DEFAULT 42, estado TEXT NULL, ciudad TEXT NULL, cp TEXT NULL, direccion TEXT NULL
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('cpotencial')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, razon_social TEXT NOT NULL, marca TEXT NOT NULL, rfc TEXT NULL,
            sector_id INTEGER NULL, ejecutivo_id INTEGER NOT NULL, cliente_id INTEGER NULL,
            u_crea INTEGER NULL, u_modifica INTEGER NULL, f_creacion TEXT NULL, f_modificacion TEXT NULL,
            deleted INTEGER DEFAULT 0, _countries_id INTEGER DEFAULT 42, estado TEXT NULL, ciudad TEXT NULL, cp TEXT NULL, direccion TEXT NULL
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('contacto')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, cliente_id INTEGER NULL, cpotencial_id INTEGER NULL,
            nombre TEXT NOT NULL, telefono TEXT NOT NULL, celular TEXT NULL, otro_num TEXT NULL, puesto TEXT NULL,
            departamento TEXT NULL, correo TEXT NOT NULL, descripcion TEXT NULL, u_crea INTEGER NULL, u_modifica INTEGER NULL,
            f_creacion TEXT NULL, f_modificacion TEXT NULL, deleted INTEGER DEFAULT 0, _countries_id INTEGER DEFAULT 42,
            estado TEXT NULL, ciudad TEXT NULL, cp TEXT NULL, direccion TEXT NULL
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('propuesta')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, nombre TEXT NOT NULL, canal_id INTEGER NOT NULL, monto REAL NOT NULL,
            cliente_id INTEGER NULL, cpotencial_id INTEGER NULL, contacto_id INTEGER NOT NULL, estado_id INTEGER NOT NULL,
            ejecutivo_id INTEGER NOT NULL, descripcion TEXT NULL, u_crea INTEGER NULL, u_modifica INTEGER NULL,
            f_creacion TEXT NULL, f_modificacion TEXT NULL, deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('documento')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, cliente_id INTEGER NULL, cpotencial_id INTEGER NULL, propuesta_id INTEGER NULL,
            nombre TEXT NOT NULL, archivo_original TEXT NOT NULL, archivo_ruta TEXT NOT NULL, mime TEXT NULL, tamano INTEGER DEFAULT 0,
            u_crea INTEGER NULL, u_modifica INTEGER NULL, f_creacion TEXT NULL, f_modificacion TEXT NULL, deleted INTEGER DEFAULT 0
        )');
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('seguimiento')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT, fecha TEXT NOT NULL, hora TEXT NOT NULL, actividad_id INTEGER NOT NULL,
            descripcion TEXT NULL, adjunto TEXT NULL, estado_id INTEGER NOT NULL, cliente_id INTEGER NOT NULL,
            ejecutivo_id INTEGER NOT NULL, tipo_id INTEGER NOT NULL, propuesta_id INTEGER NULL, monto REAL NULL,
            u_crea INTEGER NULL, u_modifica INTEGER NULL, f_creacion TEXT NULL, f_modificacion TEXT NULL, deleted INTEGER DEFAULT 0
        )');
        if (method_exists($db, 'resetDataCache')) {
            $db->resetDataCache();
        }

        foreach ([1, 2, 3, 4, 5, 6] as $profileId) {
            $db->table('perfil')->insert(['id' => $profileId, 'nombre' => 'Perfil ' . $profileId, 'deleted' => 0]);
        }
        $db->table('cgestion')->insertBatch([
            ['id' => 1, 'nombre' => 'Canal Norte', 'deleted' => 0],
            ['id' => 2, 'nombre' => 'Canal Sur', 'deleted' => 0],
        ]);
        $db->table('ucomercial')->insertBatch([
            ['id' => 10, 'nombre' => 'Unidad Norte', 'deleted' => 0],
            ['id' => 20, 'nombre' => 'Unidad Sur', 'deleted' => 0],
        ]);
        $db->table('sector')->insert(['id' => 1, 'nombre' => 'Retail', 'deleted' => 0]);
        $db->table('actividad')->insertBatch([
            ['id' => 1, 'nombre' => 'Llamada', 'activo' => 1, 'deleted' => 0],
            ['id' => 3, 'nombre' => 'Entrega de propuesta', 'activo' => 1, 'deleted' => 0],
        ]);
        $db->table('estado')->insertBatch([
            ['id' => 1, 'nombre' => 'Abierta', 'deleted' => 0],
            ['id' => 2, 'nombre' => 'Ganada', 'deleted' => 0],
        ]);

        $this->insertUser(1, 'Administrador', 1, 10);
        $this->insertUser(2, 'Gerente Norte', 2, 10);
        $this->insertUser(3, 'Ejecutivo Norte', 3, 10);
        $this->insertUser(4, 'Ejecutivo Sur', 3, 20);
        $this->insertUser(5, 'Consulta', 4, 10);

        $db->table('cliente')->insertBatch([
            ['id' => 10, 'razon_social' => 'Cliente Norte', 'marca' => 'Marca Norte', 'rfc' => 'CLN010101AAA', 'sector_id' => 1, 'cgestion_id' => 1, 'ejecutivo_id' => 3, 'deleted' => 0],
            ['id' => 11, 'razon_social' => 'Cliente Sur', 'marca' => 'Marca Sur', 'rfc' => 'CLS010101AAA', 'sector_id' => 1, 'cgestion_id' => 2, 'ejecutivo_id' => 4, 'deleted' => 0],
        ]);
        $db->table('cpotencial')->insertBatch([
            ['id' => 20, 'razon_social' => 'Prospecto Norte', 'marca' => 'Marca PN', 'rfc' => 'PRN010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 3, 'cliente_id' => null, 'deleted' => 0],
            ['id' => 21, 'razon_social' => 'Prospecto Sur', 'marca' => 'Marca PS', 'rfc' => 'PRS010101AAA', 'sector_id' => 1, 'ejecutivo_id' => 4, 'cliente_id' => null, 'deleted' => 0],
        ]);
        $db->table('contacto')->insertBatch([
            ['id' => 100, 'cliente_id' => 10, 'cpotencial_id' => null, 'nombre' => 'Contacto Norte', 'telefono' => '555', 'correo' => 'norte@example.com', 'deleted' => 0],
            ['id' => 101, 'cliente_id' => 11, 'cpotencial_id' => null, 'nombre' => 'Contacto Sur', 'telefono' => '555', 'correo' => 'sur@example.com', 'deleted' => 0],
            ['id' => 102, 'cliente_id' => null, 'cpotencial_id' => 20, 'nombre' => 'Contacto Prospecto', 'telefono' => '555', 'correo' => 'prospecto@example.com', 'deleted' => 0],
        ]);
        $db->table('propuesta')->insertBatch([
            ['id' => 200, 'nombre' => 'Propuesta Norte', 'canal_id' => 1, 'monto' => 1000, 'cliente_id' => 10, 'cpotencial_id' => null, 'contacto_id' => 100, 'estado_id' => 1, 'ejecutivo_id' => 3, 'u_crea' => 1, 'deleted' => 0],
            ['id' => 201, 'nombre' => 'Propuesta Sur', 'canal_id' => 2, 'monto' => 2000, 'cliente_id' => 11, 'cpotencial_id' => null, 'contacto_id' => 101, 'estado_id' => 1, 'ejecutivo_id' => 4, 'u_crea' => 1, 'deleted' => 0],
        ]);
    }

    public function testProposalCrudRowsScopeDocumentsAndSoftDelete(): void
    {
        $teamRows = $this->withSession($this->sessionFor(2, 2, 10))->post('propuesta/get_rows', $this->withCsrf([
            'draw' => '4',
            'search' => ['value' => 'Propuesta'],
        ]));
        $teamRows->assertOK();
        $payload = json_decode((string) $teamRows->getJSON(), true);
        $this->assertSame(1, $payload['recordsTotal']);
        $this->assertSame('Propuesta Norte', $payload['data'][0]['nombre']);

        $create = $this->withSession($this->sessionFor(2, 2, 10))->post('propuesta/add', $this->withCsrf([
            'nombre' => ' Nueva Propuesta ',
            'canal_id' => 1,
            'monto' => '999.50',
            'cliente_id' => '10_1',
            'contacto_id' => 100,
            'estado_id' => 1,
            'descripcion' => 'Demo',
        ]));
        $created = db_connect()->table('propuesta')->where('nombre', 'Nueva Propuesta')->get()->getRowArray();
        $this->assertNotNull($created);
        $create->assertRedirectTo('propuesta/' . $created['id']);
        $this->assertSame(10, (int) $created['cliente_id']);
        $this->assertSame(2, (int) $created['u_crea']);

        $service = new ProposalService();
        $documentId = $service->attachDocuments((int) $created['id'], [$this->uploadedFile('propuesta.pdf', '%PDF')], $this->sessionFor(2, 2, 10)['user'], 'team', 2);
        $this->assertCount(1, $documentId);
        $this->assertSame(1, (int) db_connect()->table('documento')->where('propuesta_id', $created['id'])->countAllResults());

        $detail = $this->withSession($this->sessionFor(2, 2, 10))->get('propuesta/' . $created['id']);
        $detail->assertOK();
        $detail->assertSee('Nueva Propuesta');
        $detail->assertSee('propuesta.pdf');

        $outOfScope = $this->withSession($this->sessionFor(2, 2, 10))->get('propuesta/201');
        $outOfScope->assertStatus(404);

        $edit = $this->withSession($this->sessionFor(2, 2, 10))->post('propuesta/' . $created['id'] . '/edit', $this->withCsrf([
            'nombre' => 'Propuesta Actualizada',
            'canal_id' => 1,
            'monto' => '1200.00',
            'cliente_id' => '10_1',
            'contacto_id' => 100,
            'estado_id' => 2,
            'descripcion' => 'Actualizada',
        ]));
        $edit->assertRedirectTo('propuesta/' . $created['id']);
        $updated = db_connect()->table('propuesta')->where('id', $created['id'])->get()->getRowArray();
        $this->assertSame('Propuesta Actualizada', $updated['nombre']);
        $this->assertSame(2, (int) $updated['estado_id']);

        $delete = $this->withSession($this->sessionFor(2, 2, 10))->post('propuesta/delete/' . $created['id'], $this->withCsrf([]));
        $delete->assertJSONFragment(['exito' => true]);
        $this->assertSame(1, (int) db_connect()->table('propuesta')->select('deleted')->where('id', $created['id'])->get()->getRowArray()['deleted']);
    }

    public function testFollowUpCanLinkExistingProposalAndCreateProposalFromActivityThree(): void
    {
        $link = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/add', $this->withCsrf([
            'cliente_id' => '10_1',
            'fecha' => '2026-07-16',
            'hora' => '10:00',
            'actividad_id' => 1,
            'estado_id' => 1,
            'ejecutivo_id' => 3,
            'propuesta_id' => 200,
            'descripcion' => 'Seguimiento con propuesta',
        ]));
        $linked = db_connect()->table('seguimiento')->where('descripcion', 'Seguimiento con propuesta')->get()->getRowArray();
        $this->assertNotNull($linked);
        $link->assertRedirectTo('seguimiento/' . $linked['id']);
        $this->assertSame(200, (int) $linked['propuesta_id']);

        $mismatch = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/add', $this->withCsrf([
            'cliente_id' => '10_1',
            'fecha' => '2026-07-17',
            'hora' => '10:00',
            'actividad_id' => 1,
            'estado_id' => 1,
            'ejecutivo_id' => 3,
            'propuesta_id' => 201,
            'descripcion' => 'Seguimiento cruzado',
        ]));
        $mismatch->assertOK();
        $this->assertSame(0, (int) db_connect()->table('seguimiento')->where('descripcion', 'Seguimiento cruzado')->countAllResults());

        $auto = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/add', $this->withCsrf([
            'cliente_id' => '10_1',
            'fecha' => '2026-07-18',
            'hora' => '11:30',
            'actividad_id' => 3,
            'estado_id' => 1,
            'ejecutivo_id' => 3,
            'descripcion' => 'Entrega enviada',
            'propuesta_nombre' => 'Propuesta desde seguimiento',
            'propuesta_canal_id' => 1,
            'propuesta_monto' => '1500.00',
            'propuesta_contacto_id' => 100,
            'propuesta_estado_id' => 1,
        ]));
        $createdProposal = db_connect()->table('propuesta')->where('nombre', 'Propuesta desde seguimiento')->get()->getRowArray();
        $createdFollowUp = db_connect()->table('seguimiento')->where('descripcion', 'Entrega enviada')->get()->getRowArray();
        $this->assertNotNull($createdProposal);
        $this->assertNotNull($createdFollowUp);
        $auto->assertRedirectTo('seguimiento/' . $createdFollowUp['id']);
        $this->assertSame((int) $createdProposal['id'], (int) $createdFollowUp['propuesta_id']);

        $incomplete = $this->withSession($this->sessionFor(2, 2, 10))->post('seguimiento/add', $this->withCsrf([
            'cliente_id' => '10_1',
            'fecha' => '2026-07-19',
            'hora' => '12:00',
            'actividad_id' => 3,
            'estado_id' => 1,
            'ejecutivo_id' => 3,
            'descripcion' => 'Entrega incompleta',
        ]));
        $incomplete->assertOK();
        $this->assertSame(0, (int) db_connect()->table('seguimiento')->where('descripcion', 'Entrega incompleta')->countAllResults());
    }

    public function testFollowUpFormFromProposalSubpanelIsPrefilled(): void
    {
        $form = $this->withSession($this->sessionFor(2, 2, 10))->get('seguimiento/add?propuesta_id=200');
        $form->assertOK();
        $body = (string) $form->getBody();
        $this->assertStringContainsString('value="200" selected', $body);
        $this->assertStringContainsString('value="10_1" selected', $body);
    }

    public function testProposalServiceValidatesContactActivityAndRollbackCleansStoredFiles(): void
    {
        $service = new ProposalService();
        $identity = $this->sessionFor(2, 2, 10)['user'];
        $this->assertTrue($service->activityThreeIsProposalDelivery());

        try {
            $service->create([
                'nombre' => 'Contacto incorrecto',
                'canal_id' => 1,
                'monto' => 123,
                'cliente_id' => '10_1',
                'contacto_id' => 101,
                'estado_id' => 1,
                'ejecutivo_id' => 3,
            ], [], $identity, 'team', 2);
            $this->fail('Expected mismatched contact to be rejected.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $before = $this->storedDocumentFiles();
        try {
            $service->create([
                'nombre' => 'Rollback archivos',
                'canal_id' => 1,
                'monto' => 456,
                'cliente_id' => '10_1',
                'contacto_id' => 100,
                'estado_id' => 1,
                'ejecutivo_id' => 3,
            ], [
                $this->uploadedFile('ok.pdf', '%PDF test'),
                $this->uploadedFile('bad.php', '<?php echo 1;'),
            ], $identity, 'team', 2);
            $this->fail('Expected invalid second document to rollback proposal creation.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, (int) db_connect()->table('propuesta')->where('nombre', 'Rollback archivos')->countAllResults());
        $this->assertSame($before, $this->storedDocumentFiles());
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
        $db->table('usuario_ucomercial')->insert(['usuario_id' => $id, 'ucomercial_id' => $unitId, 'deleted' => 0]);
    }

    private function uploadedFile(string $name, string $contents): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'proposal-test-');
        file_put_contents($path, $contents);
        return new UploadedFile($path, $name, 'application/octet-stream', strlen($contents), UPLOAD_ERR_OK);
    }

    /** @return list<string> */
    private function storedDocumentFiles(): array
    {
        $dir = WRITEPATH . 'uploads' . DIRECTORY_SEPARATOR . 'documents';
        if (! is_dir($dir)) {
            return [];
        }
        $files = array_map('basename', glob($dir . DIRECTORY_SEPARATOR . '*') ?: []);
        sort($files);
        return $files;
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

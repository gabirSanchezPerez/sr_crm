<?php

use App\Services\AuthorizationService;
use App\Services\DocumentService;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;
use Config\Services;

final class DocumentFeatureTest extends CIUnitTestCase
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
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('documento')) . ' (
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
        )');        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('contacto')) . ' (
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

        $this->writeStoredFile('documents/existing-norte.pdf', 'norte');
        $this->writeStoredFile('documents/existing-sur.pdf', 'sur');
        $db->table('documento')->insertBatch([
            ['id' => 100, 'cliente_id' => 10, 'cpotencial_id' => null, 'nombre' => 'Documento Cliente Norte', 'archivo_original' => 'norte.pdf', 'archivo_ruta' => 'documents/existing-norte.pdf', 'mime' => 'application/pdf', 'tamano' => 5, 'u_crea' => 1, 'deleted' => 0],
            ['id' => 101, 'cliente_id' => 11, 'cpotencial_id' => null, 'nombre' => 'Documento Cliente Sur', 'archivo_original' => 'sur.pdf', 'archivo_ruta' => 'documents/existing-sur.pdf', 'mime' => 'application/pdf', 'tamano' => 3, 'u_crea' => 1, 'deleted' => 0],
            ['id' => 102, 'cliente_id' => null, 'cpotencial_id' => 20, 'nombre' => 'Documento Prospecto Norte', 'archivo_original' => 'prospecto.pdf', 'archivo_ruta' => 'documents/existing-norte.pdf', 'mime' => 'application/pdf', 'tamano' => 5, 'u_crea' => 1, 'deleted' => 0],
            ['id' => 103, 'cliente_id' => 12, 'cpotencial_id' => null, 'nombre' => 'Documento Padre Eliminado', 'archivo_original' => 'baja.pdf', 'archivo_ruta' => 'documents/existing-norte.pdf', 'mime' => 'application/pdf', 'tamano' => 5, 'u_crea' => 1, 'deleted' => 0],
            ['id' => 104, 'cliente_id' => 10, 'cpotencial_id' => null, 'nombre' => 'Documento Eliminado', 'archivo_original' => 'eliminado.pdf', 'archivo_ruta' => 'documents/existing-norte.pdf', 'mime' => 'application/pdf', 'tamano' => 5, 'u_crea' => 1, 'deleted' => 1],
        ]);
    }

    public function testDocumentRowsRespectParentScopeAndInactiveParents(): void
    {
        $team = $this->withSession($this->sessionFor(2, 2, 10))->post('documento/get_rows', $this->withCsrf([
            'draw' => '7',
            'search' => ['value' => 'Documento'],
        ]));

        $team->assertOK();
        $payload = json_decode((string) $team->getJSON(), true);
        $this->assertSame(2, $payload['recordsTotal']);
        $this->assertSame(['Documento Prospecto Norte', 'Documento Cliente Norte'], array_column($payload['data'], 'nombre'));
        $this->assertSame(['download', 'delete'], array_column($payload['data'][0]['_actions'], 'name'));
    }

    public function testDocumentUploadStoresMetadataAndRejectsUnsafeFiles(): void
    {
        $service = new DocumentService();
        $id = $service->create(
            ['parent_type' => 'cliente', 'parent_id' => 10, 'nombre' => ' Contrato Norte '],
            $this->uploadedFile('contrato.pdf', '%PDF test'),
            $this->sessionFor(2, 2, 10)['user'],
            'team',
            2
        );

        $created = db_connect()->table('documento')->where('id', $id)->get()->getRowArray();
        $this->assertSame('Contrato Norte', $created['nombre']);
        $this->assertSame(10, (int) $created['cliente_id']);
        $this->assertNull($created['cpotencial_id']);
        $this->assertSame('contrato.pdf', $created['archivo_original']);
        $this->assertStringStartsWith('documents/', $created['archivo_ruta']);
        $this->assertFileExists(WRITEPATH . 'uploads/' . str_replace('/', DIRECTORY_SEPARATOR, $created['archivo_ruta']));
        $this->assertSame(2, (int) $created['u_crea']);
        $this->assertNotEmpty($created['f_creacion']);

        try {
            $service->create(['parent_type' => 'cliente', 'parent_id' => 10], $this->uploadedFile('shell.php', '<?php'), $this->sessionFor(2, 2, 10)['user'], 'team', 2);
            $this->fail('Expected unsafe extension to be rejected.');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }
        $this->assertSame(0, (int) db_connect()->table('documento')->where('archivo_original', 'shell.php')->countAllResults());
    }

    public function testDocumentDownloadAndDeleteRequireParentScope(): void
    {
        $download = $this->withSession($this->sessionFor(2, 2, 10))->get('documento/download/100');
        $this->assertSame(200, $download->response()->getStatusCode());

        $outOfScopeDownload = $this->withSession($this->sessionFor(2, 2, 10))->get('documento/download/101');
        $outOfScopeDownload->assertStatus(404);

        $deleteOutOfScope = $this->withSession($this->sessionFor(2, 2, 10))->post('documento/delete/101', $this->withCsrf([]));
        $deleteOutOfScope->assertStatus(404);
        $this->assertSame(0, (int) db_connect()->table('documento')->select('deleted')->where('id', 101)->get()->getRowArray()['deleted']);

        $delete = $this->withSession($this->sessionFor(2, 2, 10))->post('documento/delete/100', $this->withCsrf([]));
        $delete->assertJSONFragment(['exito' => true]);
        $this->assertSame(1, (int) db_connect()->table('documento')->select('deleted')->where('id', 100)->get()->getRowArray()['deleted']);
    }

    public function testCustomerAndProspectFormsRenderDocumentSubpanels(): void
    {
        $customer = $this->withSession($this->sessionFor(1, 1, 10))->get('cliente/10');
        $customer->assertOK();
        $customer->assertSee('Documentos');
        $customer->assertSee('Documento Cliente Norte');

        $prospect = $this->withSession($this->sessionFor(4, 5, 10))->get('cpotencial/20');
        $prospect->assertOK();
        $prospect->assertSee('Documentos');
        $prospect->assertSee('Documento Prospecto Norte');
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
        $path = tempnam(sys_get_temp_dir(), 'doc-test-');
        file_put_contents($path, $contents);
        return new UploadedFile($path, $name, 'application/octet-stream', strlen($contents), UPLOAD_ERR_OK);
    }

    private function writeStoredFile(string $relativePath, string $contents): void
    {
        $path = WRITEPATH . 'uploads/' . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        file_put_contents($path, $contents);
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
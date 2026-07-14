<?php

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CatalogActionPresenter;
use App\Catalogs\CatalogField;
use App\Catalogs\CatalogModel;
use App\Catalogs\CatalogPresenter;
use App\Catalogs\CatalogService;
use App\Services\AuthorizationService;
use CodeIgniter\Test\CIUnitTestCase;

final class CatalogPatternTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $db = db_connect();
        $db->query('DROP TABLE IF EXISTS ' . $db->escapeIdentifiers($db->prefixTable('estado')));
        $db->query('CREATE TABLE ' . $db->escapeIdentifiers($db->prefixTable('estado')) . ' (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nombre TEXT NOT NULL,
            deleted INTEGER DEFAULT 0,
            u_crea INTEGER NULL,
            f_creacion TEXT NULL,
            u_modifica INTEGER NULL,
            f_modificacion TEXT NULL
        )');
    }

    public function testCatalogDefinitionBuildsFieldListsAndValidationRules(): void
    {
        $definition = $this->definition('required|is_unique[estado.nombre,id,{id}]');

        $this->assertSame(['nombre'], $definition->fieldNames());
        $this->assertCount(1, $definition->listFields());
        $this->assertSame(
            ['nombre' => 'required|is_unique[estado.nombre,id,5]'],
            $definition->validationRules(5),
        );
    }

    public function testCatalogServiceCreatesUpdatesListsAndSoftDeletesWithAuditFields(): void
    {
        $service = new CatalogService(new CatalogModel($this->definition()));

        $id = $service->create(['nombre' => ' Activo '], 7);
        $created = $service->find($id);

        $this->assertSame('Activo', $created['nombre']);
        $this->assertSame(7, (int) $created['u_crea']);
        $this->assertNotEmpty($created['f_creacion']);

        $service->update($id, ['nombre' => 'Actualizado'], 9);
        $updated = $service->find($id);

        $this->assertSame('Actualizado', $updated['nombre']);
        $this->assertSame(9, (int) $updated['u_modifica']);
        $this->assertNotEmpty($updated['f_modificacion']);
        $this->assertCount(1, $service->rows('Actual'));

        $service->softDelete($id, 11);

        $this->assertNull($service->find($id));
        $this->assertSame(0, count($service->rows()));
        $stored = db_connect()->table('estado')->where('id', $id)->get()->getRowArray();
        $this->assertSame(1, (int) $stored['deleted']);
        $this->assertSame(11, (int) $stored['u_modifica']);
    }

    public function testCatalogPresenterShapesSharedViewAndFormPayloads(): void
    {
        $definition = $this->definition();
        $presenter = new CatalogPresenter($definition);

        $index = $presenter->indexData(
            [['id' => 1, 'nombre' => 'Activo']],
            [['name' => 'add', 'label' => 'Nuevo', 'icon' => 'feather-plus', 'url' => 'estado/add', 'method' => 'GET', 'style' => 'primary']],
            [1 => [['name' => 'edit', 'label' => 'Editar', 'icon' => 'feather-edit-2', 'url' => 'estado/1', 'method' => 'GET', 'style' => 'outline-primary']]],
        );

        $this->assertSame($definition, $index['catalog']);
        $this->assertSame('Estados | CRM', $index['title']);
        $this->assertSame('add', $index['pageActions'][0]['name']);
        $this->assertSame('edit', $index['rowActions'][1][0]['name']);


        $form = $presenter->formData(['nombre' => 'Activo'], [], false);
        $this->assertSame('Editar Estado | CRM', $form['title']);
        $this->assertFalse($form['isNew']);
    }

    public function testCatalogActionPresenterDerivesActionsFromAuthorization(): void
    {
        $definition = new CatalogDefinition(
            module: 'marca',
            table: 'marca',
            route: 'marca',
            singularLabel: 'Marca',
            pluralLabel: 'Marcas',
            fields: [new CatalogField('nombre', 'Nombre', 'required')],
        );
        $actions = new CatalogActionPresenter($definition, new AuthorizationService(), 1);

        $this->assertSame(['add'], array_column($actions->pageActions(), 'name'));
        $this->assertSame(['edit', 'delete'], array_column($actions->rowActions(['id' => 9]), 'name'));

        $unauthorized = new CatalogActionPresenter($definition, new AuthorizationService(), 3);
        $this->assertSame([], $unauthorized->pageActions());
        $this->assertSame([], $unauthorized->rowActions(['id' => 9]));

        $readOnlyDefinition = new CatalogDefinition(
            module: 'estado',
            table: 'estado',
            route: 'estado',
            singularLabel: 'Estado',
            pluralLabel: 'Estados',
            fields: [new CatalogField('nombre', 'Nombre', 'required')],
            supportsDelete: false,
        );
        $readOnlyActions = new CatalogActionPresenter($readOnlyDefinition, new AuthorizationService(), 1);

        $this->assertSame(['edit'], array_column($readOnlyActions->rowActions(['id' => 3]), 'name'));
    }

    private function definition(string $rules = 'required|min_length[2]|max_length[150]'): CatalogDefinition
    {
        return new CatalogDefinition(
            module: 'estado',
            table: 'estado',
            route: 'estado',
            singularLabel: 'Estado',
            pluralLabel: 'Estados',
            fields: [new CatalogField('nombre', 'Nombre', $rules)],
        );
    }
}

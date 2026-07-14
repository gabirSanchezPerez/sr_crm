<?php

namespace App\Controllers;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CatalogField;

final class Marca extends CatalogController
{
    protected function definition(): CatalogDefinition
    {
        return new CatalogDefinition(
            module: 'marca',
            table: 'marca',
            route: 'marca',
            singularLabel: 'Marca',
            pluralLabel: 'Marcas',
            fields: [
                new CatalogField('nombre', 'Nombre', 'required|min_length[2]|max_length[150]|is_unique[marca.nombre,id,{id}]'),
            ],
        );
    }
}

<?php

namespace App\Controllers;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CatalogField;

final class Sector extends CatalogController
{
    protected function definition(): CatalogDefinition
    {
        return new CatalogDefinition(
            module: 'sector',
            table: 'sector',
            route: 'sector',
            singularLabel: 'Sector',
            pluralLabel: 'Sectores',
            fields: [
                new CatalogField('nombre', 'Nombre', 'required|min_length[2]|max_length[150]|is_unique[sector.nombre,id,{id}]'),
            ],
            supportsDelete: false,
        );
    }
}

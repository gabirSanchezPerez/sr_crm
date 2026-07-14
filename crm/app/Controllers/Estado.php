<?php

namespace App\Controllers;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CatalogField;

final class Estado extends CatalogController
{
    protected function definition(): CatalogDefinition
    {
        return new CatalogDefinition(
            module: 'estado',
            table: 'estado',
            route: 'estado',
            singularLabel: 'Estado',
            pluralLabel: 'Estados',
            fields: [
                new CatalogField('nombre', 'Nombre', 'required|min_length[2]|max_length[150]|is_unique[estado.nombre,id,{id}]'),
            ],
            supportsDelete: false,
        );
    }
}

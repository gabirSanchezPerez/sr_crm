<?php

namespace App\Controllers;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CatalogField;

final class CGestion extends CatalogController
{
    protected function definition(): CatalogDefinition
    {
        return new CatalogDefinition(
            module: 'cgestion',
            table: 'cgestion',
            route: 'cgestion',
            singularLabel: 'Canal de gestion',
            pluralLabel: 'Canales de gestion',
            fields: [
                new CatalogField('nombre', 'Nombre', 'required|min_length[2]|max_length[150]|is_unique[cgestion.nombre,id,{id}]'),
            ],
        );
    }
}

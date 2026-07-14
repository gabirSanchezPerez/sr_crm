<?php

namespace App\Controllers;

use App\Catalogs\CatalogDefinition;
use App\Catalogs\CatalogField;

final class UComercial extends CatalogController
{
    protected function definition(): CatalogDefinition
    {
        return new CatalogDefinition(
            module: 'ucomercial',
            table: 'ucomercial',
            route: 'ucomercial',
            singularLabel: 'Unidad comercial',
            pluralLabel: 'Unidades comerciales',
            fields: [
                new CatalogField('nombre', 'Nombre', 'required|min_length[2]|max_length[150]|is_unique[ucomercial.nombre,id,{id}]'),
            ],
        );
    }
}

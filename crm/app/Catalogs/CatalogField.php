<?php

namespace App\Catalogs;

final class CatalogField
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $rules = 'required|min_length[2]|max_length[150]',
        public readonly string $type = 'text',
        public readonly bool $listable = true,
    ) {
    }
}

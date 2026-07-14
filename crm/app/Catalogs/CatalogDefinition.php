<?php

namespace App\Catalogs;

final class CatalogDefinition
{
    /**
     * @param list<CatalogField> $fields
     */
    public function __construct(
        public readonly string $module,
        public readonly string $table,
        public readonly string $route,
        public readonly string $singularLabel,
        public readonly string $pluralLabel,
        public readonly array $fields,
        public readonly string $orderBy = 'nombre',
        public readonly bool $supportsDelete = true,
    ) {
    }

    /**
     * @return list<string>
     */
    public function fieldNames(): array
    {
        return array_map(static fn (CatalogField $field): string => $field->name, $this->fields);
    }

    /**
     * @return list<CatalogField>
     */
    public function listFields(): array
    {
        return array_values(array_filter(
            $this->fields,
            static fn (CatalogField $field): bool => $field->listable,
        ));
    }

    /**
     * @return array<string, string>
     */
    public function validationRules(?int $id = null): array
    {
        $rules = [];
        foreach ($this->fields as $field) {
            $rule = $field->rules;
            if ($id !== null) {
                $rule = str_replace('{id}', (string) $id, $rule);
            }
            $rules[$field->name] = $rule;
        }

        return $rules;
    }
}

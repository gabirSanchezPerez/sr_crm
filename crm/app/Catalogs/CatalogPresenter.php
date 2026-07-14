<?php

namespace App\Catalogs;

final class CatalogPresenter
{
    public function __construct(private readonly CatalogDefinition $definition)
    {
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<array<string, string>> $pageActions
     * @param array<int, list<array<string, string>>> $rowActions
     * @return array<string, mixed>
     */
    public function indexData(array $rows, array $pageActions, array $rowActions): array
    {
        return [
            'catalog' => $this->definition,
            'rows' => $rows,
            'listFields' => $this->definition->listFields(),
            'pageActions' => $pageActions,
            'rowActions' => $rowActions,
            'title' => $this->definition->pluralLabel . ' | CRM',
            'heading' => $this->definition->pluralLabel,
            'breadcrumbs' => ['Inicio' => site_url('home'), $this->definition->pluralLabel => null],
            'permissions' => session('permissions') ?? [],
        ];
    }

    /**
     * @param array<string, mixed> $record
     * @param array<string, string> $errors
     * @return array<string, mixed>
     */
    public function formData(array $record, array $errors, bool $isNew): array
    {
        return [
            'catalog' => $this->definition,
            'record' => $record,
            'errors' => $errors,
            'isNew' => $isNew,
            'title' => ($isNew ? 'Nuevo ' : 'Editar ') . $this->definition->singularLabel . ' | CRM',
            'heading' => $isNew ? 'Nuevo ' . $this->definition->singularLabel : 'Editar ' . $this->definition->singularLabel,
            'breadcrumbs' => [
                'Inicio' => site_url('home'),
                $this->definition->pluralLabel => site_url($this->definition->route),
                $isNew ? 'Nuevo' : 'Editar' => null,
            ],
            'permissions' => session('permissions') ?? [],
        ];
    }

}

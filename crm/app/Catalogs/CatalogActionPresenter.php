<?php

namespace App\Catalogs;

use App\Services\AuthorizationService;

final class CatalogActionPresenter
{
    public function __construct(
        private readonly CatalogDefinition $definition,
        private readonly AuthorizationService $authorization,
        private readonly int $profileId,
    ) {
    }

    public function can(string $operation): bool
    {
        if ($operation === 'delete' && ! $this->definition->supportsDelete) {
            return false;
        }

        return $this->authorization->allows($this->profileId, $this->definition->module, $operation);
    }

    /**
     * @return list<array<string, string>>
     */
    public function pageActions(): array
    {
        if (! $this->can('add')) {
            return [];
        }

        return [[
            'name' => 'add',
            'label' => 'Nuevo',
            'icon' => 'feather-plus',
            'url' => site_url($this->definition->route . '/add'),
            'method' => 'GET',
            'style' => 'primary',
        ]];
    }

    /**
     * @param array<string, mixed> $row
     * @return list<array<string, string>>
     */
    public function rowActions(array $row): array
    {
        $id = (int) ($row['id'] ?? 0);
        if ($id <= 0) {
            return [];
        }

        $actions = [];
        if ($this->can('edit')) {
            $actions[] = [
                'name' => 'edit',
                'label' => 'Editar',
                'icon' => 'feather-edit-2',
                'url' => site_url($this->definition->route . '/' . $id),
                'method' => 'GET',
                'style' => 'outline-primary',
            ];
        }

        if ($this->can('delete')) {
            $actions[] = [
                'name' => 'delete',
                'label' => 'Desactivar',
                'icon' => 'feather-trash-2',
                'url' => site_url($this->definition->route . '/delete/' . $id),
                'method' => 'POST',
                'style' => 'outline-danger',
            ];
        }

        return $actions;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<int, list<array<string, string>>>
     */
    public function rowActionsById(array $rows): array
    {
        $actions = [];
        foreach ($rows as $row) {
            $id = (int) ($row['id'] ?? 0);
            if ($id > 0) {
                $actions[$id] = $this->rowActions($row);
            }
        }

        return $actions;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    public function rowsWithActions(array $rows): array
    {
        return array_map(
            fn (array $row): array => array_merge($row, ['_actions' => $this->rowActions($row)]),
            $rows,
        );
    }
}

<?php

namespace App\Services;

final class AuthorizationService
{
    private const CRUD = ['index', 'add', 'edit', 'delete'];

    /** Legacy-compatible grants. Missing profile/module/operation is denied. */
    private const GRANTS = [
        1 => [
            'usuario' => self::CRUD, 'cliente' => self::CRUD, 'cpotencial' => ['index', 'convert'],
            'ucomercial' => self::CRUD, 'marca' => self::CRUD, 'cgestion' => self::CRUD,
            'auth' => self::CRUD, 'perfil' => self::CRUD, 'dashboard' => self::CRUD,
            'sector' => self::CRUD, 'estado' => self::CRUD, 'seguimiento' => self::CRUD,
            'contacto' => self::CRUD, 'documento' => self::CRUD, 'reporte' => self::CRUD,
        ],
        2 => [
            'usuario' => self::CRUD, 'cliente' => ['index'], 'cpotencial' => ['index'],
            'ucomercial' => self::CRUD, 'marca' => self::CRUD, 'cgestion' => self::CRUD,
            'auth' => self::CRUD, 'dashboard' => self::CRUD, 'perfil' => ['edit'],
            'seguimiento' => self::CRUD, 'contacto' => self::CRUD, 'documento' => self::CRUD, 'reporte' => ['index'],
        ],
        3 => [
            'cliente' => ['index'], 'cpotencial' => ['index'], 'auth' => ['edit'],
            'dashboard' => self::CRUD, 'seguimiento' => self::CRUD, 'contacto' => self::CRUD, 'documento' => self::CRUD, 'reporte' => ['index'],
        ],
        4 => [
            'cliente' => ['index'], 'cpotencial' => ['index', 'add', 'edit', 'delete', 'convert'], 'auth' => ['edit'],
            'dashboard' => self::CRUD, 'seguimiento' => ['index'], 'contacto' => ['index'], 'documento' => ['index'],
        ],
        5 => [
            'usuario' => ['index'], 'cliente' => ['index'], 'cpotencial' => ['index'],
            'ucomercial' => self::CRUD, 'marca' => self::CRUD, 'cgestion' => self::CRUD,
            'auth' => self::CRUD, 'dashboard' => self::CRUD, 'perfil' => ['edit'],
            'seguimiento' => ['index'], 'contacto' => ['index'], 'documento' => ['index'],
        ],
        6 => [
            'usuario' => ['index'], 'cliente' => ['index'], 'cpotencial' => ['index'],
            'ucomercial' => self::CRUD, 'marca' => self::CRUD, 'cgestion' => self::CRUD,
            'auth' => self::CRUD, 'dashboard' => self::CRUD, 'perfil' => ['edit'],
            'seguimiento' => ['index'], 'contacto' => ['index'], 'documento' => ['index'],
        ],
    ];

    private const SCOPES = [
        2 => ['usuario' => 'team', 'cliente' => 'team', 'cpotencial' => 'team', 'seguimiento' => 'team', 'contacto' => 'team', 'documento' => 'team'],
        3 => ['usuario' => 'owner', 'cliente' => 'owner', 'cpotencial' => 'owner', 'seguimiento' => 'owner', 'contacto' => 'owner', 'documento' => 'owner'],
        4 => ['usuario' => 'owner'],
        5 => ['usuario' => 'team', 'cliente' => 'team', 'cpotencial' => 'team', 'seguimiento' => 'team', 'contacto' => 'team', 'documento' => 'team'],
        6 => ['usuario' => 'team', 'cliente' => 'team', 'cpotencial' => 'team', 'seguimiento' => 'team', 'contacto' => 'team', 'documento' => 'team'],
    ];

    public function allows(int $profileId, string $module, string $operation): bool
    {
        return in_array($operation, self::GRANTS[$profileId][$module] ?? [], true);
    }

    public function scope(int $profileId, string $module): string
    {
        if (! isset(self::GRANTS[$profileId][$module])) {
            return 'none';
        }
        return self::SCOPES[$profileId][$module] ?? 'all';
    }

    /** @return list<string> */
    public function permissionsForProfile(int $profileId): array
    {
        $permissions = [];
        foreach (self::GRANTS[$profileId] ?? [] as $module => $operations) {
            foreach ($operations as $operation) {
                $permissions[] = $module . '.' . $operation;
            }
        }
        sort($permissions);
        return $permissions;
    }

    public function recordIsInScope(array $identity, string $module, int $ownerId, ?int $unitId = null): bool
    {
        return match ($this->scope((int) ($identity['perfil_id'] ?? 0), $module)) {
            'all' => true,
            'owner' => (int) ($identity['user_id'] ?? 0) === $ownerId,
            'team' => $unitId !== null && (int) ($identity['ucomercial_id'] ?? 0) === $unitId,
            'none' => false,
            default => false,
        };
    }
}


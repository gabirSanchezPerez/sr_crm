<?php

namespace App\Services;

final class DashboardService
{
    public function __construct(private readonly AuthorizationService $authorization = new AuthorizationService())
    {
    }

    /** @return array<string, mixed> */
    public function summary(array $identity, ?int $year = null): array
    {
        $profileId = (int) ($identity['perfil_id'] ?? 0);
        $customersScope = $this->authorization->scope($profileId, 'cliente');
        $prospectsScope = $this->authorization->scope($profileId, 'cpotencial');
        $followUpsScope = $this->authorization->scope($profileId, 'seguimiento');

        $cards = [
            'customers' => ['label' => 'Clientes', 'value' => $this->accountCount('cliente', $identity, $customersScope, false), 'icon' => 'feather-users'],
            'prospects' => ['label' => 'Potenciales', 'value' => $this->accountCount('cpotencial', $identity, $prospectsScope, false), 'icon' => 'feather-target'],
            'followUps' => ['label' => 'Seguimientos', 'value' => $this->followUpCount($identity, $followUpsScope), 'icon' => 'feather-calendar'],
            'conversions' => ['label' => 'Conversiones', 'value' => $this->accountCount('cpotencial', $identity, $prospectsScope, true), 'icon' => 'feather-repeat'],
            'customerBrands' => ['label' => 'Marcas clientes', 'value' => $this->brandCount('cliente', $identity, $customersScope, false), 'icon' => 'feather-bookmark'],
            'prospectBrands' => ['label' => 'Marcas potenciales', 'value' => $this->brandCount('cpotencial', $identity, $prospectsScope, false), 'icon' => 'feather-bookmark'],
        ];

        $year ??= (int) date('Y');
        return [
            'cards' => $cards,
            'chart' => [
                'labels' => array_column($cards, 'label'),
                'series' => array_map(static fn (array $card): int => (int) $card['value'], array_values($cards)),
            ],
            'forecast' => (new ForecastService())->annual($year, $identity),
        ];
    }

    private function accountCount(string $table, array $identity, string $scope, bool $converted): int
    {
        $alias = $table === 'cliente' ? 'c' : 'cp';
        $builder = db_connect()->table($table . ' ' . $alias)
            ->select('COUNT(DISTINCT ' . $alias . '.id) AS total', false)
            ->join('usuario_ucomercial uuc', 'uuc.usuario_id = ' . $alias . '.ejecutivo_id AND uuc.deleted = 0', 'left')
            ->where($alias . '.deleted', 0);

        if ($table === 'cpotencial') {
            $converted ? $builder->where($alias . '.cliente_id IS NOT', null) : $builder->where($alias . '.cliente_id', null);
        }

        $this->applyAccountScope($builder, $alias, $identity, $scope);

        return (int) ($builder->get()->getRowArray()['total'] ?? 0);
    }

    private function brandCount(string $table, array $identity, string $scope, bool $converted): int
    {
        $alias = $table === 'cliente' ? 'c' : 'cp';
        $builder = db_connect()->table($table . ' ' . $alias)
            ->select('COUNT(DISTINCT ' . $alias . '.marca) AS total', false)
            ->join('usuario_ucomercial uuc', 'uuc.usuario_id = ' . $alias . '.ejecutivo_id AND uuc.deleted = 0', 'left')
            ->where($alias . '.deleted', 0)
            ->where($alias . '.marca !=', '');

        if ($table === 'cpotencial') {
            $converted ? $builder->where($alias . '.cliente_id IS NOT', null) : $builder->where($alias . '.cliente_id', null);
        }

        $this->applyAccountScope($builder, $alias, $identity, $scope);

        return (int) ($builder->get()->getRowArray()['total'] ?? 0);
    }

    private function followUpCount(array $identity, string $scope): int
    {
        $builder = db_connect()->table('seguimiento s')
            ->select('COUNT(DISTINCT s.id) AS total', false)
            ->join('usuario_ucomercial uuc', 'uuc.usuario_id = s.ejecutivo_id AND uuc.deleted = 0', 'left')
            ->where('s.deleted', 0);

        if ($scope === 'owner') {
            $builder->where('s.ejecutivo_id', (int) ($identity['user_id'] ?? 0));
        } elseif ($scope === 'team') {
            $builder->where('uuc.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0));
        } elseif ($scope !== 'all') {
            $builder->where('1 =', 0, false);
        }

        return (int) ($builder->get()->getRowArray()['total'] ?? 0);
    }

    private function applyAccountScope(object $builder, string $alias, array $identity, string $scope): void
    {
        if ($scope === 'owner') {
            $builder->where($alias . '.ejecutivo_id', (int) ($identity['user_id'] ?? 0));
            return;
        }

        if ($scope === 'team') {
            $builder->where('uuc.ucomercial_id', (int) ($identity['ucomercial_id'] ?? 0));
            return;
        }

        if ($scope !== 'all') {
            $builder->where('1 =', 0, false);
        }
    }
}

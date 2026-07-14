<?php

namespace App\Support;

use CodeIgniter\HTTP\IncomingRequest;

final class DataTableResponder
{
    private const DEFAULT_LENGTH = 10;
    private const MAX_LENGTH = 500;
    private const MAX_SEARCH_LENGTH = 200;

    public function search(IncomingRequest $request): string
    {
        $search = $request->getGetPost('search');
        if (is_array($search)) {
            $search = $search['value'] ?? '';
        }

        return mb_substr(trim((string) ($search ?? '')), 0, self::MAX_SEARCH_LENGTH);
    }

    public function postSearch(IncomingRequest $request): string
    {
        $search = $request->getPost('search');
        if (is_array($search)) {
            $search = $search['value'] ?? '';
        }

        return mb_substr(trim((string) ($search ?? '')), 0, self::MAX_SEARCH_LENGTH);
    }

    /**
     * @param list<array<string, mixed>> $totalRows
     * @param list<array<string, mixed>> $filteredRows
     * @param callable(array<string, mixed>): array<string, mixed>|null $decorate
     * @param list<string> $orderableColumns
     * @return array<string, mixed>
     */
    public function payload(IncomingRequest $request, array $totalRows, array $filteredRows, ?callable $decorate = null, array $orderableColumns = []): array
    {
        $rows = $this->orderedRows($request, $filteredRows, $orderableColumns);
        $rows = $this->pagedRows($request, $rows);

        if ($decorate !== null) {
            $rows = array_map($decorate, $rows);
        }

        return [
            'draw' => $this->draw($request),
            'recordsTotal' => count($totalRows),
            'recordsFiltered' => count($filteredRows),
            'data' => array_values($rows),
        ];
    }

    private function draw(IncomingRequest $request): int
    {
        return max(0, (int) ($request->getGetPost('draw') ?? 0));
    }

    /** @param list<array<string, mixed>> $rows @return list<array<string, mixed>> */
    private function pagedRows(IncomingRequest $request, array $rows): array
    {
        $start = max(0, (int) ($request->getGetPost('start') ?? 0));
        $length = (int) ($request->getGetPost('length') ?? self::DEFAULT_LENGTH);
        if ($length === -1) {
            return array_slice($rows, $start);
        }
        if ($length < 1) {
            $length = self::DEFAULT_LENGTH;
        }
        $length = min($length, self::MAX_LENGTH);

        return array_slice($rows, $start, $length);
    }

    /** @param list<array<string, mixed>> $rows @param list<string> $orderableColumns @return list<array<string, mixed>> */
    private function orderedRows(IncomingRequest $request, array $rows, array $orderableColumns): array
    {
        if ($orderableColumns === []) {
            return $rows;
        }

        $order = $request->getGetPost('order');
        if (! is_array($order) || ! isset($order[0]) || ! is_array($order[0])) {
            return $rows;
        }

        $index = (int) ($order[0]['column'] ?? -1);
        $column = $orderableColumns[$index] ?? null;
        if ($column === null) {
            return $rows;
        }

        $direction = strtolower((string) ($order[0]['dir'] ?? 'asc')) === 'desc' ? -1 : 1;
        usort($rows, static function (array $left, array $right) use ($column, $direction): int {
            $leftValue = $left[$column] ?? null;
            $rightValue = $right[$column] ?? null;

            return $direction * strnatcasecmp((string) $leftValue, (string) $rightValue);
        });

        return $rows;
    }
}
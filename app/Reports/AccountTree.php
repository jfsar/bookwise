<?php

namespace App\Reports;

/**
 * Builds a nested account tree from flat account rows, rolling child amounts
 * up into their parents so statement sections respect the account hierarchy
 * (§7.19 / §7.20).
 */
final class AccountTree
{
    /**
     * @param  array<int, array{id: int, parent_id: int|null, code: string, name: string, normal_balance: string}>  $accounts
     * @param  array<int, float>  $amounts  account id => value
     * @return array<int, array<string, mixed>>
     */
    public static function build(array $accounts, array $amounts): array
    {
        $byId = collect($accounts)->keyBy('id');
        $childrenOf = [];

        foreach ($accounts as $account) {
            $childrenOf[$account['parent_id'] ?? 0][] = $account['id'];
        }

        $roots = [];

        foreach ($accounts as $account) {
            $parentId = $account['parent_id'];

            if ($parentId === null || ! $byId->has($parentId)) {
                $roots[] = $account['id'];
            }
        }

        $node = static function (int $id) use (&$node, $byId, $childrenOf, $amounts): array {
            $account = $byId[$id];
            $children = array_map($node, $childrenOf[$id] ?? []);
            $amount = $amounts[$id] ?? 0.0;
            $total = $amount + array_sum(array_map(fn (array $child) => $child['total'], $children));

            return [
                'id' => $account['id'],
                'code' => $account['code'],
                'name' => $account['name'],
                'amount' => round($amount, 4),
                'total' => round($total, 4),
                'children' => $children,
            ];
        };

        $sort = static function (array &$nodes) use (&$sort): void {
            usort($nodes, static fn (array $a, array $b) => strcmp($a['code'], $b['code']));

            foreach ($nodes as &$node) {
                $sort($node['children']);
            }
        };

        $trees = array_map($node, $roots);
        $sort($trees);

        return $trees;
    }
}

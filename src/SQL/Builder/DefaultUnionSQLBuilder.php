<?php

declare(strict_types=1);

namespace Doctrine\DBAL\SQL\Builder;

use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Query\UnionQuery;

use function count;
use function implode;

final class DefaultUnionSQLBuilder implements UnionSQLBuilder
{
    public function __construct(
        private readonly AbstractPlatform $platform,
    ) {
    }

    public function buildSQL(UnionQuery $query): string
    {
        $parts      = [];
        $modifier   = $query->isUnionDistinct() ? ' UNION ' : ' UNION ALL ';
        $unionParts = $this->prepareUnionParts($query);
        $parts[]    = implode($modifier, $unionParts);

        $orderBy = $query->getOrderBy();
        if (count($orderBy) > 0) {
            $parts[] = 'ORDER BY ' . implode(', ', $orderBy);
        }

        $sql   = implode(' ', $parts);
        $limit = $query->getLimit();

        if ($limit->isDefined()) {
            $sql = $this->platform->modifyLimitQuery($sql, $limit->getMaxResults(), $limit->getFirstResult());
        }

        return $sql;
    }

    /** @return string[] */
    private function prepareUnionParts(UnionQuery $query): array
    {
        $return     = [];
        $unionParts = $query->getUnionParts();
        foreach ($unionParts as $part) {
            $return[] = (string) $part;
        }

        return $return;
    }
}

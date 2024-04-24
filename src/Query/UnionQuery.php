<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

final class UnionQuery
{
    /**
     * @internal This class should be instantiated only by {@link QueryBuilder}.
     *
     * @param string[]|QueryBuilder[] $unionParts
     * @param string[]                $orderBy
     */
    public function __construct(
        private readonly bool $unionDistinct,
        private readonly array $unionParts,
        private readonly array $orderBy,
        private readonly Limit $limit,
    ) {
    }

    public function isUnionDistinct(): bool
    {
        return $this->unionDistinct;
    }

    /** @return string[]|QueryBuilder[] */
    public function getUnionParts(): array
    {
        return $this->unionParts;
    }

    /** @return string[] */
    public function getOrderBy(): array
    {
        return $this->orderBy;
    }

    public function getLimit(): Limit
    {
        return $this->limit;
    }
}

<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Query;

use Stringable;

use function implode;
use function sprintf;

final class With implements Stringable
{
    /**
     * @param string[] $fields
     * @param string[] $dependencies
     */
    public function __construct(
        private readonly string $name,
        private readonly array $fields,
        private readonly array $dependencies,
        private readonly string|QueryBuilder $expression,
        private readonly bool $recursive,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function isRecursive(): bool
    {
        return $this->recursive;
    }

    /** @return string[] */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    public function getSQL(): string
    {
        $fields = '';

        if ($this->fields !== []) {
            $fields = sprintf(' (%s)', implode(', ', $this->fields));
        }

        return sprintf(
            '%s%s AS (%s)',
            $this->getName(),
            $fields,
            $this->expression,
        );
    }

    public function __toString(): string
    {
        return $this->getSQL();
    }
}

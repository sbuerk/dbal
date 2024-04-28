<?php

declare(strict_types=1);

namespace Doctrine\DBAL\Tests\Functional\Query;

use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\DB2Platform;
use Doctrine\DBAL\Platforms\MariaDB1060Platform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQL80Platform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\ForUpdate\ConflictResolutionMode;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Tests\FunctionalTestCase;
use Doctrine\DBAL\Tests\TestUtil;
use Doctrine\DBAL\Types\Types;

use function array_change_key_case;

use const CASE_UPPER;

final class QueryBuilderTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        $table = new Table('for_update');
        $table->addColumn('id', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);

        $this->connection->insert('for_update', ['id' => 1]);
        $this->connection->insert('for_update', ['id' => 2]);
    }

    protected function tearDown(): void
    {
        if (! $this->connection->isTransactionActive()) {
            return;
        }

        $this->connection->rollBack();
    }

    public function testForUpdateOrdinary(): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof SQLitePlatform) {
            self::markTestSkipped('Skipping on SQLite');
        }

        $qb1 = $this->connection->createQueryBuilder();
        $qb1->select('id')
            ->from('for_update')
            ->forUpdate();

        self::assertEquals([1, 2], $qb1->fetchFirstColumn());
    }

    public function testForUpdateSkipLockedWhenSupported(): void
    {
        if (! $this->platformSupportsSkipLocked()) {
            self::markTestSkipped('The database platform does not support SKIP LOCKED.');
        }

        $qb1 = $this->connection->createQueryBuilder();
        $qb1->select('id')
            ->from('for_update')
            ->where('id = 1')
            ->forUpdate();

        $this->connection->beginTransaction();

        self::assertEquals([1], $qb1->fetchFirstColumn());

        $params = TestUtil::getConnectionParams();

        if (TestUtil::isDriverOneOf('oci8')) {
            $params['driverOptions']['exclusive'] = true;
        }

        $connection2 = DriverManager::getConnection($params);

        $qb2 = $connection2->createQueryBuilder();
        $qb2->select('id')
            ->from('for_update')
            ->orderBy('id')
            ->forUpdate(ConflictResolutionMode::SKIP_LOCKED);

        self::assertEquals([2], $qb2->fetchFirstColumn());
    }

    public function testForUpdateSkipLockedWhenNotSupported(): void
    {
        if ($this->platformSupportsSkipLocked()) {
            self::markTestSkipped('The database platform supports SKIP LOCKED.');
        }

        $qb = $this->connection->createQueryBuilder();
        $qb->select('id')
            ->from('for_update')
            ->forUpdate(ConflictResolutionMode::SKIP_LOCKED);

        self::expectException(Exception::class);
        $qb->executeQuery();
    }

    public function testUnionAllReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 1], ['field_one' => 1], ['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $plainSelect1 = $platform->getDummySelectSQL('2 as field_one');
        $plainSelect2 = $platform->getDummySelectSQL('1 as field_one');
        $plainSelect3 = $platform->getDummySelectSQL('1 as field_one');
        $qb           = $this->connection->createQueryBuilder();
        $qb->unionAll($plainSelect1, $plainSelect2, $plainSelect3)->orderBy('field_one', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 1], ['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $plainSelect1 = $platform->getDummySelectSQL('2 as field_one');
        $plainSelect2 = $platform->getDummySelectSQL('1 as field_one');
        $plainSelect3 = $platform->getDummySelectSQL('1 as field_one');
        $qb           = $this->connection->createQueryBuilder();
        $qb->union($plainSelect1, $plainSelect2, $plainSelect3)->orderBy('field_one', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testOnlyAddUnionAllReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 1], ['field_one' => 1], ['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $plainSelect1 = $platform->getDummySelectSQL('2 as field_one');
        $plainSelect2 = $platform->getDummySelectSQL('1 as field_one');
        $plainSelect3 = $platform->getDummySelectSQL('1 as field_one');
        $qb           = $this->connection->createQueryBuilder();
        $qb->addUnionAll($plainSelect1, $plainSelect2, $plainSelect3)->orderBy('field_one', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testOnlyAddUnionReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 1], ['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $plainSelect1 = $platform->getDummySelectSQL('2 as field_one');
        $plainSelect2 = $platform->getDummySelectSQL('1 as field_one');
        $plainSelect3 = $platform->getDummySelectSQL('1 as field_one');
        $qb           = $this->connection->createQueryBuilder();
        $qb->addUnion($plainSelect1, $plainSelect2, $plainSelect3)->orderBy('field_one', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAllAndAddUnionAllReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 1], ['field_one' => 1], ['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $plainSelect1 = $platform->getDummySelectSQL('2 as field_one');
        $plainSelect2 = $platform->getDummySelectSQL('1 as field_one');
        $plainSelect3 = $platform->getDummySelectSQL('1 as field_one');
        $qb           = $this->connection->createQueryBuilder();
        $qb->unionAll($plainSelect1)->addUnionAll($plainSelect2, $plainSelect3)->orderBy('field_one', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAndAddUnionReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 1], ['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $plainSelect1 = $platform->getDummySelectSQL('2 as field_one');
        $plainSelect2 = $platform->getDummySelectSQL('1 as field_one');
        $plainSelect3 = $platform->getDummySelectSQL('1 as field_one');
        $qb           = $this->connection->createQueryBuilder();
        $qb->union($plainSelect1)->addUnion($plainSelect2, $plainSelect3)->orderBy('field_one', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAllWithDescOrderByReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 2], ['field_one' => 1], ['field_one' => 1]]);
        $platform     = $this->connection->getDatabasePlatform();
        $plainSelect1 = $platform->getDummySelectSQL('1 as field_one');
        $plainSelect2 = $platform->getDummySelectSQL('2 as field_one');
        $plainSelect3 = $platform->getDummySelectSQL('1 as field_one');
        $qb           = $this->connection->createQueryBuilder();
        $qb->unionAll($plainSelect1, $plainSelect2, $plainSelect3)->orderBy('field_one', 'DESC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionWithDescOrderByReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 2], ['field_one' => 1]]);
        $platform     = $this->connection->getDatabasePlatform();
        $plainSelect1 = $platform->getDummySelectSQL('1 as field_one');
        $plainSelect2 = $platform->getDummySelectSQL('2 as field_one');
        $plainSelect3 = $platform->getDummySelectSQL('1 as field_one');
        $qb           = $this->connection->createQueryBuilder();
        $qb->union($plainSelect1, $plainSelect2, $plainSelect3)->orderBy('field_one', 'DESC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAllWithLimitClauseReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $plainSelect1 = $platform->getDummySelectSQL('1 as field_one');
        $plainSelect2 = $platform->getDummySelectSQL('2 as field_one');
        $plainSelect3 = $platform->getDummySelectSQL('1 as field_one');
        $qb           = $this->connection->createQueryBuilder();
        $qb->unionAll($plainSelect1, $plainSelect2, $plainSelect3)
           ->setMaxResults(1)
           ->setFirstResult(0)
           ->orderBy('field_one', 'DESC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionWithLimitClauseReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $plainSelect1 = $platform->getDummySelectSQL('1 as field_one');
        $plainSelect2 = $platform->getDummySelectSQL('2 as field_one');
        $plainSelect3 = $platform->getDummySelectSQL('1 as field_one');
        $qb           = $this->connection->createQueryBuilder();
        $qb->union($plainSelect1, $plainSelect2, $plainSelect3)
            ->setMaxResults(1)
            ->setFirstResult(0)
            ->orderBy('field_one', 'DESC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAllWithLimitAndOffsetClauseReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 1]]);
        $platform     = $this->connection->getDatabasePlatform();
        $plainSelect1 = $platform->getDummySelectSQL('1 as field_one');
        $plainSelect2 = $platform->getDummySelectSQL('2 as field_one');
        $plainSelect3 = $platform->getDummySelectSQL('1 as field_one');
        $qb           = $this->connection->createQueryBuilder();
        $qb->unionAll($plainSelect1, $plainSelect2, $plainSelect3)
            ->setMaxResults(1)
            ->setFirstResult(1)
            ->orderBy('field_one', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionWithLimitAndOffsetClauseReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['field_one' => 2]]);
        $platform     = $this->connection->getDatabasePlatform();
        $plainSelect1 = $platform->getDummySelectSQL('1 as field_one');
        $plainSelect2 = $platform->getDummySelectSQL('2 as field_one');
        $plainSelect3 = $platform->getDummySelectSQL('1 as field_one');
        $qb           = $this->connection->createQueryBuilder();
        $qb->union($plainSelect1, $plainSelect2, $plainSelect3)
            ->setMaxResults(1)
            ->setFirstResult(1)
            ->orderBy('field_one', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAllAndAddUnionAllWorksWithQueryBuilderPartsAndOrderByDescAndReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['id' => 2], ['id' => 1], ['id' => 1]]);
        $qb           = $this->connection->createQueryBuilder();

        $subQueryBuilder1 = $this->connection->createQueryBuilder();
        $subQueryBuilder1->select('id')->from('for_update')->where($qb->expr()->eq('id', '1'));

        $subQueryBuilder2 = $this->connection->createQueryBuilder();
        $subQueryBuilder2->select('id')->from('for_update')->where($qb->expr()->eq('id', '2'));

        $subQueryBuilder3 = $this->connection->createQueryBuilder();
        $subQueryBuilder3->select('id')->from('for_update')->where($qb->expr()->eq('id', '1'));

        $qb->unionAll($subQueryBuilder1)
            ->addUnionAll($subQueryBuilder2, $subQueryBuilder3)
            ->orderBy('id', 'DESC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAndAddUnionWithNamedParameterOnOuterInstanceAndOrderByDescWorks(): void
    {
        $expectedRows = $this->prepareExpectedRows([['id' => 2], ['id' => 1]]);
        $qb           = $this->connection->createQueryBuilder();

        $subQueryBuilder1 = $this->connection->createQueryBuilder();
        $subQueryBuilder1->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(1, ParameterType::INTEGER)));

        $subQueryBuilder2 = $this->connection->createQueryBuilder();
        $subQueryBuilder2->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(2, ParameterType::INTEGER)));

        $subQueryBuilder3 = $this->connection->createQueryBuilder();
        $subQueryBuilder3->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(1, ParameterType::INTEGER)));

        $qb->union($subQueryBuilder1)->addUnion($subQueryBuilder2, $subQueryBuilder3)->orderBy('id', 'DESC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAllAndAddUnionAllWorksWithQueryBuilderPartsAndReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['id' => 1], ['id' => 1], ['id' => 2]]);
        $qb           = $this->connection->createQueryBuilder();

        $subQueryBuilder1 = $this->connection->createQueryBuilder();
        $subQueryBuilder1->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(1, ParameterType::INTEGER)));

        $subQueryBuilder2 = $this->connection->createQueryBuilder();
        $subQueryBuilder2->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(2, ParameterType::INTEGER)));

        $subQueryBuilder3 = $this->connection->createQueryBuilder();
        $subQueryBuilder3->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(1, ParameterType::INTEGER)));

        $qb->unionAll($subQueryBuilder1)
            ->addUnionAll($subQueryBuilder2, $subQueryBuilder3)
            ->orderBy('id', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testUnionAndAddUnionWorksWithQueryBuilderPartsAndReturnsExpectedResult(): void
    {
        $expectedRows = $this->prepareExpectedRows([['id' => 1], ['id' => 2]]);
        $qb           = $this->connection->createQueryBuilder();

        $subQueryBuilder1 = $this->connection->createQueryBuilder();
        $subQueryBuilder1->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(1, ParameterType::INTEGER)));

        $subQueryBuilder2 = $this->connection->createQueryBuilder();
        $subQueryBuilder2->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(2, ParameterType::INTEGER)));

        $subQueryBuilder3 = $this->connection->createQueryBuilder();
        $subQueryBuilder3->select('id')
            ->from('for_update')
            ->where($qb->expr()->eq('id', $qb->createNamedParameter(1, ParameterType::INTEGER)));

        $qb->union($subQueryBuilder1)
            ->addUnion($subQueryBuilder2, $subQueryBuilder3)
            ->orderBy('id', 'ASC');

        self::assertSame($expectedRows, $qb->executeQuery()->fetchAllAssociative());
    }

    public function testWithAndAddWithReturnsExpectedResult(): void
    {
        if (! $this->connection->getDatabasePlatform()->supportsCommonTableExpressions()) {
            self::markTestSkipped('Common Table Expressions not supported.');
        }

        $records         = [
            ['id' => 1, 'parent_id' => 0, 'type' => 1],
            ['id' => 2, 'parent_id' => 0, 'type' => 2],
            ['id' => 3, 'parent_id' => 1, 'type' => 1],
        ];
        $expectedRecords = [
            ['id' => 3, 'parent_id' => 1, 'type' => 1],
        ];
        $records         = $this->prepareExpectedRows($records);
        $expectedRecords = $this->prepareExpectedRows($expectedRecords);
        $this->prepareWithDataSet(['common_table_expression' => $records]);

        $qb   = $this->connection->createQueryBuilder();
        $sub1 = $this->connection->createQueryBuilder();
        $sub1->select('id', 'parent_id', 'type')
            ->from($this->connection->quoteIdentifier('common_table_expression'))
            ->where(
                $qb->expr()->eq(
                    $this->connection->quoteIdentifier('type'),
                    $qb->createNamedParameter(1, ParameterType::INTEGER),
                ),
            );
        $sub2 = $this->connection->createQueryBuilder();
        $sub2->select('id', 'parent_id', 'type')
            ->from($this->connection->quoteIdentifier('cte1'))
            ->where(
                $qb->expr()->gt(
                    $this->connection->quoteIdentifier('parent_id'),
                    $qb->createNamedParameter(0, ParameterType::INTEGER),
                ),
            );
        $qb->select('id', 'parent_id', 'type')
            ->from($this->connection->quoteIdentifier('cte2'))
            ->with(
                'cte1',
                $sub1,
                ['id', 'parent_id', 'type'],
            )
            ->addWith(
                'cte2',
                $sub2,
                ['id', 'parent_id', 'type'],
                ['cte1'],
            );
        self::assertSame($expectedRecords, $qb->executeQuery()->fetchAllAssociative());
    }

    /** @param array<string, array<int, array<string, mixed>>> $tableRecords */
    private function prepareWithDataSet(array $tableRecords): void
    {
        $this->createCommonTableExpressionTable();
        foreach ($tableRecords as $table => $records) {
            foreach ($records as $record) {
                $this->connection->insert($table, $record);
            }
        }
    }

    private function createCommonTableExpressionTable(): void
    {
        $table = new Table('common_table_expression');
        $table->addColumn('id', Types::INTEGER);
        $table->addColumn('parent_id', Types::INTEGER);
        $table->addColumn('type', Types::INTEGER);
        $table->setPrimaryKey(['id']);

        $this->dropAndCreateTable($table);
    }

    /**
     * @param array<array<string, int>> $rows
     *
     * @return array<array<string, int|string>>
     */
    private function prepareExpectedRows(array $rows): array
    {
        if (! TestUtil::isDriverOneOf('ibm_db2', 'pdo_oci', 'pdo_sqlsrv', 'oci8')) {
            return $rows;
        }

        if (! TestUtil::isDriverOneOf('ibm_db2')) {
            foreach ($rows as &$row) {
                foreach ($row as &$value) {
                    $value = (string) $value;
                }
            }
        }

        if (! TestUtil::isDriverOneOf('ibm_db2', 'pdo_oci', 'oci8')) {
            return $rows;
        }

        foreach ($rows as &$row) {
            $row = array_change_key_case($row, CASE_UPPER);
        }

        return $rows;
    }

    private function platformSupportsSkipLocked(): bool
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof DB2Platform) {
            return false;
        }

        if ($platform instanceof MySQLPlatform) {
            return $platform instanceof MySQL80Platform;
        }

        if ($platform instanceof MariaDBPlatform) {
            return $platform instanceof MariaDB1060Platform;
        }

        return ! $platform instanceof SQLitePlatform;
    }
}

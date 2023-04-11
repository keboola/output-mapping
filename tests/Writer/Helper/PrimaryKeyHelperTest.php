<?php

declare(strict_types=1);

namespace Keboola\OutputMapping\Tests\Writer\Helper;

use Keboola\Csv\CsvFile;
use Keboola\OutputMapping\Tests\AbstractTestCase;
use Keboola\OutputMapping\Tests\Needs\NeedsEmptyOutputBucket;
use Keboola\OutputMapping\Writer\Helper\PrimaryKeyHelper;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;

class PrimaryKeyHelperTest extends AbstractTestCase
{
    private function createTable(array $columns, string $primaryKey): string
    {
        $csv = new CsvFile($this->temp->getTmpFolder() . '/import.csv');
        $csv->writeRow($columns);
        return $this->clientWrapper->getBasicClient()->createTableAsync(
            $this->emptyOutputBucketId,
            'test-table',
            $csv,
            ['primaryKey' => $primaryKey]
        );
    }

    /**
     * @dataProvider normalizePrimaryKeyProvider
     * @param array $pkey
     * @param array $result
     */
    public function testNormalizePrimaryKey(array $pkey, array $result): void
    {
        self::assertEquals($result, PrimaryKeyHelper::normalizeKeyArray(new NullLogger(), $pkey));
    }

    public function normalizePrimaryKeyProvider(): array
    {
        return [
            [
                [''],
                [],
            ],
            [
                ['Id', 'Id'],
                ['Id'],
            ],
            [
                ['Id ', 'Name'],
                ['Id', 'Name'],
            ],
        ];
    }

    /**
     * @dataProvider modifyPrimaryKeyDeciderOptionsProvider
     */
    public function testModifyPrimaryKeyDecider(
        array $currentTableInfo,
        array $newTableConfiguration,
        bool $result
    ): void {
        self::assertEquals($result, PrimaryKeyHelper::modifyPrimaryKeyDecider(
            new NullLogger(),
            $currentTableInfo,
            $newTableConfiguration
        ));
    }

    /**
     * @return array
     */
    public function modifyPrimaryKeyDeciderOptionsProvider(): array
    {
        return [
            [
                [
                    'primaryKey' => [],
                ],
                [
                    'primary_key' => [],
                ],
                false,
            ],
            [
                [
                    'primaryKey' => [],
                ],
                [
                    'primary_key' => ['Id'],
                ],
                true,
            ],
            [
                [
                    'primaryKey' => ['Id'],
                ],
                [
                    'primary_key' => [],
                ],
                true,
            ],
            [
                [
                    'primaryKey' => ['Id'],
                ],
                [
                    'primary_key' => ['Id'],
                ],
                false,
            ],
            [
                [
                    'primaryKey' => ['Id'],
                ],
                [
                    'primary_key' => ['Name'],
                ],
                true,
            ],
            [
                [
                    'primaryKey' => ['Id'],
                ],
                [
                    'primary_key' => ['Id', 'Name'],
                ],
                true,
            ],
        ];
    }

    #[NeedsEmptyOutputBucket]
    public function testModifyPrimaryKeyChange(): void
    {
        $logger = new TestLogger();
        $tableId = $this->createTable(['id', 'name', 'foo'], 'id,name');
        $tableInfo = $this->clientWrapper->getBasicClient()->getTable($tableId);
        self::assertEquals(['id', 'name'], $tableInfo['primaryKey']);

        PrimaryKeyHelper::modifyPrimaryKey(
            $logger,
            $this->clientWrapper->getBasicClient(),
            $tableId,
            ['id', 'name'],
            ['id', 'foo']
        );
        self::assertTrue($logger->hasWarningThatContains(
            sprintf('Modifying primary key of table "%s" from "id, name" to "id, foo".', $tableId)
        ));
        $tableInfo = $this->clientWrapper->getBasicClient()->getTable($tableId);
        self::assertEquals(['id', 'foo'], $tableInfo['primaryKey']);
    }

    #[NeedsEmptyOutputBucket]
    public function testModifyPrimaryKeyChangeFromEmpty(): void
    {
        $logger = new TestLogger();
        $tableId = $this->createTable(['id', 'name', 'foo'], '');
        $tableInfo = $this->clientWrapper->getBasicClient()->getTable($tableId);
        self::assertEquals([], $tableInfo['primaryKey']);

        PrimaryKeyHelper::modifyPrimaryKey(
            $logger,
            $this->clientWrapper->getBasicClient(),
            $tableId,
            [ ],
            ['id', 'foo']
        );
        self::assertTrue($logger->hasWarningThatContains(
            sprintf('Modifying primary key of table "%s" from "" to "id, foo".', $tableId)
        ));
        $tableInfo = $this->clientWrapper->getBasicClient()->getTable($tableId);
        self::assertEquals(['id', 'foo'], $tableInfo['primaryKey']);
    }

    #[NeedsEmptyOutputBucket]
    public function testModifyPrimaryKeyChangeToEmpty(): void
    {
        $logger = new TestLogger();
        $tableId = $this->createTable(['id', 'name', 'foo'], 'id,foo');
        $tableInfo = $this->clientWrapper->getBasicClient()->getTable($tableId);
        self::assertEquals(['id', 'foo'], $tableInfo['primaryKey']);

        PrimaryKeyHelper::modifyPrimaryKey(
            $logger,
            $this->clientWrapper->getBasicClient(),
            $tableId,
            $tableInfo['primaryKey'],
            []
        );
        self::assertTrue($logger->hasWarningThatContains(
            sprintf('Modifying primary key of table "%s" from "id, foo" to "".', $tableId)
        ));
        $tableInfo = $this->clientWrapper->getBasicClient()->getTable($tableId);
        self::assertEquals([], $tableInfo['primaryKey']);
    }

    #[NeedsEmptyOutputBucket]
    public function testModifyPrimaryKeyErrorRemove(): void
    {
        $logger = new TestLogger();
        $tableId = $this->createTable(['id', 'name', 'foo'], 'id,name');
        $tableInfo = $this->clientWrapper->getBasicClient()->getTable($tableId);
        self::assertEquals(['id', 'name'], $tableInfo['primaryKey']);
        $invalidTableId = $tableId . '-non-existent';

        PrimaryKeyHelper::modifyPrimaryKey(
            $logger,
            $this->clientWrapper->getBasicClient(),
            $invalidTableId,
            ['id', 'name'],
            ['id', 'foo']
        );
        self::assertTrue($logger->hasWarningThatContains(
            sprintf('Modifying primary key of table "%s" from "id, name" to "id, foo".', $invalidTableId)
        ));
        self::assertTrue($logger->hasWarningThatContains(
            sprintf(
                'Error deleting primary key of table %s: The table "test-table-non-existent" ' .
                'was not found in the bucket "%s" in the project',
                $invalidTableId,
                $this->emptyOutputBucketId
            )
        ));
        $tableInfo = $this->clientWrapper->getBasicClient()->getTable($tableId);
        self::assertEquals(['id', 'name'], $tableInfo['primaryKey']);
    }

    #[NeedsEmptyOutputBucket]
    public function testModifyPrimaryKeyErrorCreate(): void
    {
        $logger = new TestLogger();
        $tableId = $this->createTable(['id', 'name', 'foo'], 'id,name');
        $tableInfo = $this->clientWrapper->getBasicClient()->getTable($tableId);
        self::assertEquals(['id', 'name'], $tableInfo['primaryKey']);

        PrimaryKeyHelper::modifyPrimaryKey(
            $logger,
            $this->clientWrapper->getBasicClient(),
            $tableId,
            ['id', 'name'],
            ['id', 'bar']
        );
        self::assertTrue($logger->hasWarningThatContains(
            sprintf('Modifying primary key of table "%s" from "id, name" to "id, bar".', $tableId)
        ));
        self::assertTrue($logger->hasWarningThatContains(
            sprintf(
                'Error changing primary key of table %s: Primary key columns "bar" not found in "id, name, foo"',
                $tableId
            )
        ));
        $tableInfo = $this->clientWrapper->getBasicClient()->getTable($tableId);
        self::assertEquals(['id', 'name'], $tableInfo['primaryKey']);
    }
}

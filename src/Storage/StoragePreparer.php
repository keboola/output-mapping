<?php

declare(strict_types=1);

namespace Keboola\OutputMapping\Storage;

use Keboola\OutputMapping\Mapping\MappingFromProcessedConfiguration;
use Keboola\OutputMapping\Mapping\MappingStorageSources;
use Keboola\OutputMapping\SystemMetadata;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApiBranch\ClientWrapper;
use Psr\Log\LoggerInterface;

class StoragePreparer
{
    public function __construct(
        readonly private ClientWrapper $clientWrapper,
        readonly private LoggerInterface $logger,
        readonly private bool $hasNewNativeTypeFeature,
        readonly private bool $hasBigQueryNativeTypesFeature,
    ) {
    }

    public function prepareStorageBucketAndTable(
        MappingFromProcessedConfiguration $processedSource,
        SystemMetadata $systemMetadata,
        TableChangesStore $tableChangesStore,
    ): MappingStorageSources {
        $bucketCreator = new BucketCreator($this->clientWrapper);
        $destinationBucket = $bucketCreator->ensureDestinationBucket(
            $processedSource->getDestination(),
            $systemMetadata,
        );

        $destinationTableInfo = $this->getDestinationTableInfoIfExists(
            $processedSource->getDestination()->getTableId(),
        );

        if ($destinationTableInfo !== null) {
            if ($this->hasNewNativeTypeFeature && $processedSource->getSchema()) {
                $tableStructureModifier = new TableStructureModifierFromSchema($this->clientWrapper, $this->logger);
                $tableStructureModifier->updateTableStructure(
                    $destinationBucket,
                    $destinationTableInfo,
                    $tableChangesStore,
                );
            } else {
                $tableStructureModifier = new TableStructureModifier($this->clientWrapper, $this->logger);
                $tableStructureModifier->updateTableStructure(
                    $destinationBucket,
                    $destinationTableInfo,
                    $processedSource,
                    $processedSource->getDestination(),
                    NativeTypeDecisionHelper::shouldEnforceBaseTypes(
                        $this->hasBigQueryNativeTypesFeature,
                        $destinationBucket->backend,
                    ),
                );
            }

            $tableDataModifier = new TableDataModifier($this->clientWrapper);
            $tableDataModifier->updateTableData(
                $processedSource,
                $processedSource->getDestination(),
            );

            $destinationTableInfo = $this->getDestinationTableInfoIfExists(
                $processedSource->getDestination()->getTableId(),
            );
        }

        return new MappingStorageSources($destinationBucket, $destinationTableInfo);
    }

    private function getDestinationTableInfoIfExists(string $tableId): ?TableInfo
    {
        try {
            return new TableInfo($this->clientWrapper->getTableAndFileStorageClient()->getTable($tableId));
        } catch (ClientException $e) {
            if ($e->getCode() !== 404) {
                throw $e;
            }
        }

        return null;
    }
}

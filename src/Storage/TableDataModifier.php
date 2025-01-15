<?php

declare(strict_types=1);

namespace Keboola\OutputMapping\Storage;

use Keboola\OutputMapping\Exception\InvalidOutputException;
use Keboola\OutputMapping\Mapping\MappingFromProcessedConfiguration;
use Keboola\OutputMapping\Writer\Table\MappingDestination;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApiBranch\ClientWrapper;

class TableDataModifier
{
    public function __construct(
        private readonly ClientWrapper $clientWrapper,
    ) {
    }

    public function updateTableData(MappingFromProcessedConfiguration $source, MappingDestination $destination): void
    {
        if (!is_null($source->getDeleteWhereColumn())) {
            // Delete rows
            $deleteOptions = [
                'whereColumn' => $source->getDeleteWhereColumn(),
                'whereOperator' => $source->getDeleteWhereOperator(),
                'whereValues' => $source->getDeleteWhereValues(),
            ];
            try {
                $this->clientWrapper->getTableAndFileStorageClient()->deleteTableRows(
                    $destination->getTableId(),
                    $deleteOptions,
                );
            } catch (ClientException $e) {
                throw new InvalidOutputException(
                    sprintf(
                        'Cannot delete rows from table "%s" in Storage: %s',
                        $destination->getTableId(),
                        $e->getMessage(),
                    ),
                    $e->getCode(),
                    $e,
                );
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Keboola\OutputMapping\DeferredTasks\TableWriter;

use Keboola\OutputMapping\DeferredTasks\LoadTableTaskInterface;
use Keboola\OutputMapping\DeferredTasks\Metadata\MetadataInterface;
use Keboola\OutputMapping\Writer\Table\MappingDestination;
use Keboola\StorageApi\Metadata;

abstract class AbstractLoadTableTask implements LoadTableTaskInterface
{
    protected MappingDestination $destination;
    protected array $options;
    protected bool $freshlyCreatedTable;
    protected string $storageJobId;
    /** @var MetadataInterface[] */
    protected array $metadata = [];

    public function __construct(MappingDestination $destination, array $options, bool $freshlyCreatedTable)
    {
        $this->destination = $destination;
        $this->options = $options;
        $this->freshlyCreatedTable = $freshlyCreatedTable;
    }

    public function addMetadata(MetadataInterface $metadataDefinition): void
    {
        $this->metadata[] = $metadataDefinition;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function applyMetadata(Metadata $metadataApiClient): void
    {
        foreach ($this->metadata as $metadataDefinition) {
            $metadataDefinition->apply($metadataApiClient);
        }
    }

    public function getDestinationTableName(): string
    {
        return $this->destination->getTableId();
    }

    public function getStorageJobId(): string
    {
        return $this->storageJobId;
    }

    public function isUsingFreshlyCreatedTable(): bool
    {
        return $this->freshlyCreatedTable;
    }
}

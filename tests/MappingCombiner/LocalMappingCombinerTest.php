<?php

declare(strict_types=1);

namespace Keboola\OutputMapping\Tests\Mapping;

use Keboola\OutputMapping\Mapping\MappingFromRawConfiguration;
use Keboola\OutputMapping\Mapping\MappingFromRawConfigurationAndPhysicalData;
use Keboola\OutputMapping\Mapping\MappingFromRawConfigurationAndPhysicalDataWithManifest;
use Keboola\OutputMapping\MappingCombiner\LocalMappingCombiner;
use Keboola\OutputMapping\Writer\FileItem;
use PHPUnit\Framework\TestCase;

class LocalMappingCombinerTest extends TestCase
{
    private LocalMappingCombiner $combiner;

    protected function setUp(): void
    {
        $this->combiner = new LocalMappingCombiner();
    }

    public function testCombineDataItemsWithConfigurations(): void
    {
        $dataItems = [
            new FileItem('pathName1', 'path1', 'name1', false),
            new FileItem('pathName2', 'path2', 'name2', false),
        ];
        $configurations = [
            new MappingFromRawConfiguration(['source' => 'name1']),
            new MappingFromRawConfiguration(['source' => 'name2']),
        ];

        $combined = $this->combiner->combineDataItemsWithConfigurations($dataItems, $configurations);

        $this->assertCount(2, $combined);
        $this->assertInstanceOf(MappingFromRawConfigurationAndPhysicalData::class, $combined[0]);
        $this->assertInstanceOf(MappingFromRawConfigurationAndPhysicalData::class, $combined[1]);
    }

    public function testCombineDataItemsWithConfigurationsEmptyConfiguration(): void
    {
        $dataItems = [
            new FileItem('pathName1', 'path1', 'name1', false),
        ];
        $configurations = [];

        $combined = $this->combiner->combineDataItemsWithConfigurations($dataItems, $configurations);

        $this->assertCount(1, $combined);
        $this->assertInstanceOf(MappingFromRawConfigurationAndPhysicalData::class, $combined[0]);

        $mapping = $combined[0];
        $this->assertNull($mapping->getConfiguration());
    }

    public function testCombineSourcesWithManifests(): void
    {
        $dataItem = new FileItem('pathName', 'path', 'name', false);
        $mappingItem = new MappingFromRawConfiguration(['source' => 'source']);
        $sources = [
            new MappingFromRawConfigurationAndPhysicalData($dataItem, $mappingItem),
        ];
        $manifests = [
            new FileItem('manifestPathName', 'manifestPath', 'name.manifest', false),
        ];

        $combined = $this->combiner->combineSourcesWithManifests($sources, $manifests);

        $this->assertCount(1, $combined);
        $this->assertInstanceOf(MappingFromRawConfigurationAndPhysicalDataWithManifest::class, $combined[0]);

        $mapping = $combined[0];
        $this->assertNotNull($mapping->getManifest());
        $this->assertEquals('manifestPathName', $mapping->getManifest()->getPathName());
        $this->assertEquals('manifestPath', $mapping->getManifest()->getPath());
    }

    public function testCombineSourcesWithManifestsNoManifests(): void
    {
        $dataItem = new FileItem('pathName', 'path', 'name', false);
        $mappingItem = new MappingFromRawConfiguration(['source' => 'source']);
        $sources = [
            new MappingFromRawConfigurationAndPhysicalData($dataItem, $mappingItem),
        ];
        $manifests = [];

        $combined = $this->combiner->combineSourcesWithManifests($sources, $manifests);

        $this->assertCount(1, $combined);
        $this->assertInstanceOf(MappingFromRawConfigurationAndPhysicalDataWithManifest::class, $combined[0]);

        $mapping = $combined[0];
        $this->assertNull($mapping->getManifest());
    }
}

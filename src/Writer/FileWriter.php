<?php

declare(strict_types=1);

namespace Keboola\OutputMapping\Writer;

use Keboola\InputMapping\Reader;
use Keboola\InputMapping\State\InputFileStateList;
use Keboola\OutputMapping\Configuration\File\Manifest as FileManifest;
use Keboola\OutputMapping\Configuration\TableFile;
use Keboola\OutputMapping\Exception\InvalidOutputException;
use Keboola\OutputMapping\Exception\OutputOperationException;
use Keboola\OutputMapping\Staging\StrategyFactory;
use Keboola\OutputMapping\SystemMetadata;
use Keboola\OutputMapping\Writer\Helper\TagsHelper;
use Keboola\StorageApi\ClientException;
use Keboola\StorageApiBranch\ClientWrapper;
use Psr\Log\LoggerInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;

class FileWriter
{
    public function __construct(
        private readonly ClientWrapper $clientWrapper,
        private readonly LoggerInterface $logger,
        private readonly StrategyFactory $strategyFactory,
    ) {
    }

    /**
     * Upload files from local temp directory to Storage.
     *
     * @param string $source Source path.
     * @param array $configuration Upload configuration
     * @param array $systemMetadata Metadata identifying the source of the file
     * @param array $tableFiles For the use file storage only case, tags etc are provided here
     * @param bool $isFailedJob Marks that the writer was called as part of a failed job and only
     *  write_always OM is to be processed. Since this flag is not currently implemented for files,
     *  it means that no files will be uploaded.
     */
    public function uploadFiles(
        string $source,
        array $configuration,
        array $systemMetadata,
        array $tableFiles,
        bool $isFailedJob,
    ): void {
        if ($isFailedJob) {
            return;
        }
        if (!empty($systemMetadata) && empty($systemMetadata[SystemMetadata::SYSTEM_KEY_COMPONENT_ID])) {
            throw new OutputOperationException('Component Id must be set');
        }
        $strategy = $this->strategyFactory->getFileOutputStrategy();
        $files = $strategy->listFiles($source);
        $manifests = $strategy->listManifests($source);

        $outputMappingFiles = [];
        if (isset($configuration['mapping'])) {
            foreach ($configuration['mapping'] as $mapping) {
                $outputMappingFiles[] = $mapping['source'];
            }
        }
        $outputMappingFiles = array_unique($outputMappingFiles);
        $processedOutputMappingFiles = [];

        $fileNames = [];
        foreach ($files as $file) {
            $fileNames[] = $file->getName();
        }
        // Check if all files from output mappings are present
        if (isset($configuration['mapping'])) {
            foreach ($configuration['mapping'] as $mapping) {
                if (!in_array($mapping['source'], $fileNames)) {
                    throw new InvalidOutputException("File '{$mapping["source"]}' not found.", 404);
                }
            }
        }

        // Check for manifest orphans
        foreach ($manifests as $manifest) {
            if (!in_array(substr(basename($manifest->getName()), 0, -9), $fileNames)) {
                throw new InvalidOutputException(
                    'Found orphaned file manifest: \'' . basename($manifest->getName()) . "'",
                );
            }
        }

        foreach ($files as $file) {
            $configFromMapping = [];
            $configFromManifest = [];
            if (isset($configuration['mapping'])) {
                foreach ($configuration['mapping'] as $mapping) {
                    if (isset($mapping['source']) && $mapping['source'] === $file->getName()) {
                        $configFromMapping = $mapping;
                        $processedOutputMappingFiles[] = $configFromMapping['source'];
                        unset($configFromMapping['source']);
                    }
                }
            }
            $manifestKey = $file->getPathName() . '.manifest';
            // If $tableFiles are supplied then we don't want the manifest because it'll be a table manifest
            if (isset($manifests[$manifestKey]) && empty($tableFiles)) {
                $configFromManifest = $strategy->readFileManifest($file->getPathName() . '.manifest');
                unset($manifests[$manifestKey]);
            }
            try {
                if (!empty($tableFiles)) {
                    // tableFiles take highest priority
                    $storageConfig = (new TableFile())->parse([$tableFiles]);
                } elseif ($configFromMapping || !$configFromManifest) {
                    // Mapping with higher priority than manifest
                    $storageConfig = (new FileManifest())->parse([$configFromMapping]);
                } else {
                    $storageConfig = (new FileManifest())->parse([$configFromManifest]);
                }
            } catch (InvalidConfigurationException $e) {
                throw new InvalidOutputException(
                    "Failed to write manifest for table {$file->getPathName()}.",
                    0,
                    $e,
                );
            }
            try {
                if ($systemMetadata) {
                    $storageConfig = TagsHelper::addSystemTags(
                        $storageConfig,
                        new SystemMetadata($systemMetadata),
                    );
                }
                if (!$this->clientWrapper->getClientOptionsReadOnly()->useBranchStorage()) {
                    $storageConfig = TagsHelper::rewriteTags($storageConfig, $this->clientWrapper);
                }
                $strategy->loadFileToStorage($file->getPathName(), $storageConfig);
            } catch (ClientException $e) {
                throw new InvalidOutputException(
                    "Cannot upload file '{$file->getName()}' to Storage API: " . $e->getMessage(),
                    $e->getCode(),
                    $e,
                );
            }
        }

        $processedOutputMappingFiles = array_unique($processedOutputMappingFiles);
        $diff = array_diff(
            array_merge($outputMappingFiles, $processedOutputMappingFiles),
            $processedOutputMappingFiles,
        );
        if (count($diff)) {
            throw new InvalidOutputException(
                "Couldn't process output mapping for file(s) '" . join("', '", $diff) . "'.",
            );
        }
    }

    /**
     * Add tags to processed input files.
     * @param $configuration array
     */
    public function tagFiles(array $configuration): void
    {
        // processed_tags are disabled for real branch storage
        // https://github.com/keboola/platform-libraries/pull/135/files#diff-e0fdfb86e35c6693c4179557bf8a093ac9c9cf51cf888af18201ee4fe789c367R74
        if ($this->clientWrapper->getClientOptionsReadOnly()->useBranchStorage()) {
            return;
        }
        $prefix = null;
        if ($this->clientWrapper->isDevelopmentBranch()) {
            $prefix = $this->clientWrapper->getBranchId();
        }
        foreach ($configuration as $fileConfiguration) {
            if (!empty($fileConfiguration['processed_tags'])) {
                $fileInputOptions = Reader::getFiles(
                    $fileConfiguration,
                    $this->clientWrapper,
                    $this->logger,
                );
                $listFileOptions = $fileInputOptions->getStorageApiFileListOptions(new InputFileStateList([]));
                $files = $this->clientWrapper->getTableAndFileStorageClient()->listFiles($listFileOptions);
                foreach ($files as $file) {
                    foreach ($fileConfiguration['processed_tags'] as $tag) {
                        $this->clientWrapper->getTableAndFileStorageClient()->addFileTag(
                            $file['id'],
                            $prefix ? $prefix . '-' . $tag : $tag,
                        );
                    }
                }
            }
        }
    }
}

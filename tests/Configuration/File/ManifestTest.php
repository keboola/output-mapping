<?php

namespace Keboola\OutputMapping\Tests\Configuration\File;

use Keboola\OutputMapping\Configuration\File\Manifest;

class ManifestTest extends \PHPUnit_Framework_TestCase
{

    public function testConfiguration()
    {
        $config = ['tags' => ['tag1', 'tag2']];
        $expectedResponse = [
            'is_public' => false,
            'is_permanent' => false,
            'is_encrypted' => true,
            'notify' => false,
            'tags' => ['tag1', 'tag2'],
        ];
        $processedConfiguration = (new Manifest())->parse(['config' => $config]);
        $this->assertEquals($expectedResponse, $processedConfiguration);
    }
}

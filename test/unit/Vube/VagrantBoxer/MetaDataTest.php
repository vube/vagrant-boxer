<?php
/**
 * @author Ross Perkins <ross@vubeology.com>
 */

namespace unit\Vube\VagrantBoxer;

use org\bovigo\vfs\vfsStream;
use Vube\VagrantBoxer\MetaData;


class MetaDataTest extends \PHPUnit_Framework_TestCase {

	private $root;

	/**
	 * Before each test, create a predictable root filesystem with
	 * some basic known fixtures.
	 */
	public function setUp()
	{
		$this->root = vfsStream::setup('root', null, array(
            'empty-dir' => array(),
            'existing-dir' => array(
                'metadata.json' => '
{
    "name": "foo\/bar",
    "versions": [
        {
            "version": "1.0.0",
            "providers": [
                {
                    "name": "virtualbox",
                    "url": "{{download_url_prefix}}{{path_info}}\/foo-bar-1.0.2-virtualbox.box",
                    "checksum_type": "sha1",
                    "checksum": "da39a3ee5e6b4b0d3255bfef95601890afd80709"
                }
            ]
        }
    ]
}',
            ),
		));
	}

	public function testMetaDataConstruct()
	{
        $name = 'test';
        $metadata = new MetaData($name);

        $data = $metadata->dataAsArray();
        $this->assertTrue(is_array($data), "dataAsArray() must return array");

        $this->assertSame($name, $metadata->get('name'), "Name doesn't match");
        $this->assertEquals(array(), $metadata->get('versions'), "Should have empty versions array");
        $this->assertSame(0, $metadata->getNumVersions(), "Should have zero versions");
        $this->assertNull($metadata->getActiveVersionNumber(), "Should be no active version number");
	}

    public function testMetaDataLoadEmptyDir()
    {
        $missingMetadataFile = vfsStream::url('root/empty-dir/metadata.json');

        $name = 'test';
        $metadata = new MetaData($name);
        $result = $metadata->loadFromFile($missingMetadataFile);

        $this->assertSame(MetaData::METADATA_DEFAULT, $result, "Expected default metadata");
        $this->assertTrue($metadata->isDefault(), "Expected isDefault()==true");
    }

    public function testMetaDataLoadExistingJson()
    {
        $metadataFile = vfsStream::url('root/existing-dir/metadata.json');

        $name = 'test';
        $metadata = new MetaData($name);
        $result = $metadata->loadFromFile($metadataFile);

        $this->assertSame(MetaData::METADATA_CUSTOM, $result, "Expected custom metadata");
        $this->assertFalse($metadata->isDefault(), "Expected isDefault()==false");

        $this->assertSame(1, $metadata->getNumVersions(), "Should have 1 entry in versions array");
        $this->assertSame('1.0.0', $metadata->getActiveVersionNumber(), "Should be active version 1.0.0");
    }
}

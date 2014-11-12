<?php
/**
 * @author Ross Perkins <ross@vubeology.com>
 */

namespace unit\Vube\VagrantBoxer;

use org\bovigo\vfs\vfsStream;
use Vube\VagrantBoxer\MetaData;


class MetaDataTest extends \PHPUnit_Framework_TestCase {

    const DEFAULT_METADATA_NAME = 'test';

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
                'invalid.json' => 'this is not json!',
                'metadata.json' => '
{
    "name": "' . self::DEFAULT_METADATA_NAME . '",
    "versions": [
        {
            "version": "1.0.0",
            "providers": [
                {
                    "name": "virtualbox",
                    "url": "{{download_url_prefix}}{{path_info}}\/test-1.0.0-virtualbox.box",
                    "checksum_type": "sha1",
                    "checksum": "da39a3ee5e6b4b0d3255bfef95601890afd80709"
                }
            ]
        }
    ]
}',
            ),
            'output-dir' => array(),
        ));
    }

    public function testMetaDataConstruct()
    {
        $name = self::DEFAULT_METADATA_NAME;
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

        $metadata = new MetaData(self::DEFAULT_METADATA_NAME);
        $result = $metadata->loadFromFile($missingMetadataFile);

        $this->assertSame(MetaData::METADATA_DEFAULT, $result, "Expected default metadata");
        $this->assertTrue($metadata->isDefault(), "Expected isDefault()==true");
    }

    public function testMetaDataLoadExistingJson()
    {
        $metadataFile = vfsStream::url('root/existing-dir/metadata.json');

        $metadata = new MetaData(self::DEFAULT_METADATA_NAME);
        $result = $metadata->loadFromFile($metadataFile);

        $this->assertSame(MetaData::METADATA_CUSTOM, $result, "Expected custom metadata");
        $this->assertFalse($metadata->isDefault(), "Expected isDefault()==false");

        $this->assertSame(1, $metadata->getNumVersions(), "Should have 1 entry in versions array");
        $this->assertSame('1.0.0', $metadata->getActiveVersionNumber(), "Should be active version 1.0.0");
    }

    public function testMetaDataLoadExistingJsonWithWrongName()
    {
        $metadataFile = vfsStream::url('root/existing-dir/metadata.json');

        $name = 'new/name';
        $metadata = new MetaData($name);
        $result = $metadata->loadFromFile($metadataFile);

        $this->assertSame(MetaData::METADATA_CUSTOM, $result, "Expected custom metadata");
        $this->assertFalse($metadata->isDefault(), "Expected isDefault()==false");

        $this->assertSame($name, $metadata->get('name'), "Expected metadata to take on the new name");
    }

    public function testMetaDataSetTogglesIsDefaultIfNotModified()
    {
        $metadataFile = vfsStream::url('root/existing-dir/metadata.json');

        $metadata = new MetaData(self::DEFAULT_METADATA_NAME);
        $result = $metadata->loadFromFile($metadataFile);

        $this->assertFalse($metadata->isModified(), "Expected isModified()==false before set()");
        $metadata->set('name', self::DEFAULT_METADATA_NAME); // same value as before
        $this->assertFalse($metadata->isModified(), "Expected isModified()==false after set()");
    }

    public function testMetaDataSetTogglesIsDefaultIfModified()
    {
        $metadataFile = vfsStream::url('root/existing-dir/metadata.json');

        $metadata = new MetaData(self::DEFAULT_METADATA_NAME);
        $result = $metadata->loadFromFile($metadataFile);

        $this->assertFalse($metadata->isModified(), "Expected isModified()==false before set()");
        $metadata->set('name', self::DEFAULT_METADATA_NAME . '-extra-stuff'); // a different value than before
        $this->assertTrue($metadata->isModified(), "Expected isModified()==true after set()");
    }

    public function testSaveDefaultsToNewFile()
    {
        $newFile = vfsStream::url('root/output-dir/metadata.json');

        $metadata = new MetaData(self::DEFAULT_METADATA_NAME);
        $result = $metadata->saveToFile($newFile);

        $this->assertTrue($result, "Expected saveToFile() to return true");
    }

    public function testSaveDefaultsToExistingFileReturnsFalse()
    {
        $newFile = vfsStream::url('root/existing-dir/metadata.json');

        $metadata = new MetaData(self::DEFAULT_METADATA_NAME);
        $result = $metadata->saveToFile($newFile);

        $this->assertFalse($result, "Expected saveToFile() does not overwrite existing file unless changes");
    }

    public function testSaveDefaultsToExistingFileReturnsTrueWhenForced()
    {
        $newFile = vfsStream::url('root/existing-dir/metadata.json');

        $metadata = new MetaData(self::DEFAULT_METADATA_NAME);
        $result = $metadata->saveToFile($newFile, true);

        $this->assertTrue($result, "Expected saveToFile() does overwrite existing file when forced");
    }

    public function testLoadFromFileThrowsExceptionIfInvalidJson()
    {
        $invalidFile = vfsStream::url('root/existing-dir/invalid.json');

        $metadata = new MetaData(self::DEFAULT_METADATA_NAME);

        $this->setExpectedException('\\Vube\\VagrantBoxer\\Exception\\InvalidInputException');
        $metadata->loadFromFile($invalidFile);
    }

    public function testGetVersionNumberIndexOnValidVersion()
    {
        $metadataFile = vfsStream::url('root/existing-dir/metadata.json');

        $metadata = new MetaData(self::DEFAULT_METADATA_NAME);
        $metadata->loadFromFile($metadataFile);

        $expected = 0;
        $actual = $metadata->getVersionNumberIndex('1.0.0');

        $this->assertSame($expected, $actual, "Expect 1.0.0 at index 0");
    }

    public function testGetVersionNumberIndexOnInvalidVersion()
    {
        $metadataFile = vfsStream::url('root/existing-dir/metadata.json');

        $metadata = new MetaData(self::DEFAULT_METADATA_NAME);
        $metadata->loadFromFile($metadataFile);

        $actual = $metadata->getVersionNumberIndex('no-such-version');

        $this->assertNull($actual, "Expect null for invalid versions");
    }

    public function testAddProviderToNewVersion()
    {
        $metadataFile = vfsStream::url('root/existing-dir/metadata.json');

        $metadata = new MetaData(self::DEFAULT_METADATA_NAME);
        $metadata->loadFromFile($metadataFile);

        $version = '2.0.0';
        $provider = array(
            'name' => 'provider1',
        );

        $oldNumVersions = $metadata->getNumVersions();
        $newNumVersions = $metadata->addVersionProvider($version, $provider);

        $newActiveVersion = $metadata->getActiveVersionNumber();

        $this->assertEquals($oldNumVersions + 1, $newNumVersions, "Expected to add a new version");
        $this->assertSame($version, $newActiveVersion, "Expected new version to become active");
    }

    public function testAddProviderToExistingVersion()
    {
        $metadataFile = vfsStream::url('root/existing-dir/metadata.json');

        $metadata = new MetaData(self::DEFAULT_METADATA_NAME);
        $metadata->loadFromFile($metadataFile);

        $version = $metadata->getActiveVersionNumber();
        $provider = array(
            'name' => 'provider1',
        );

        $oldNumVersions = $metadata->getNumVersions();
        $newNumVersions = $metadata->addVersionProvider($version, $provider);

        $newActiveVersion = $metadata->getActiveVersionNumber();

        $this->assertEquals($oldNumVersions, $newNumVersions, "Do not expect to add a new version");
        $this->assertSame($version, $newActiveVersion, "Expected existing active version to remain active");
    }
}

<?php
/**
 * @author Ross Perkins <ross@vubeology.com>
 */

namespace unit\Vube\VagrantBoxer;

use org\bovigo\vfs\vfsStream;
use Vube\VagrantBoxer\Boxer;


class BoxerTest extends \PHPUnit_Framework_TestCase {

	const app = '/path/to/app';

	private static $emptyPackageBox;

	private $root;

	/**
	 * Create an empty.box in the temp dir that we can use to test
	 * compression/decompression and adding metadata.json to it.
	 *
	 * PHP's PharData does NOT work with vfsStream so we're required
	 * to put this on the actual filesystem.
	 */
	public static function setUpBeforeClass()
	{
		$fixturesPath = $GLOBALS['PHPUNIT_FIXTURES_DIR'] . DIRECTORY_SEPARATOR . 'empty.box';
		$p = new \PharData("tmp.tar");

		$emptyBoxFiles = array(
			'box.ovf',
			'box-disk1.vmdk',
			'metadata.json',
			'Vagrantfile',
		);

		foreach($emptyBoxFiles as $file)
			$p->addFile("$fixturesPath/$file", basename($file));

		$p->compress(\Phar::GZ);

		unlink("tmp.tar");
		rename("tmp.tar.gz", "empty.box");

		self::$emptyPackageBox = file_get_contents("empty.box");
	}

	/**
	 * Before each test, create a predictable root filesystem with
	 * some basic known fixtures.
	 */
	public function setUp()
	{
		$this->root = vfsStream::setup('root', null, array(
			'invalid.json' => 'not valid json file',
			'empty-metadata.json' => '{}',
		));
	}

	public function testGetNextArgWithValidArgument()
	{
		$args = array('--foo', 'foo value');
		$box = new Boxer;
		$foo = $box->getNextArg($args, 0);
		$this->assertSame($args[1], $foo);
	}

	public function testGetNextArgWithMissingArgument()
	{
		$args = array('--foo');
		$box = new Boxer;
		$this->setExpectedException('\Vube\VagrantBoxer\Exception\MissingArgumentException');
		$foo = $box->getNextArg($args, 0);
	}

	public function testDefaultMetaData()
	{
		$box = new Boxer;
		$metadata = $box->getDefaultMetaData();
		$this->assertArrayHasKey('provider', $metadata);
		$this->assertArrayHasKey('versions', $metadata);
	}

	public function testDefaultBoxerConfigRequiresBaseName()
	{
		$box = new Boxer;
		$this->setExpectedException('\Vube\VagrantBoxer\Exception');
		$box->getDefaultBoxerConfig();
	}

	public function testDefaultBoxerConfigWithBaseName()
	{
		$basename = 'basename';
		$args = array(self::app, '--base', $basename);

		$box = new Boxer;
		$box->readCommandLine($args);
		$conf = $box->getDefaultBoxerConfig();

		$this->assertArrayHasKey('vm-name', $conf);
		$this->assertSame($basename, $conf['vm-name']);
		$this->assertArrayHasKey('version', $conf);
		$this->assertArrayHasKey('url-template', $conf);
	}

	protected function constructBoxer($args)
	{
		// Always set a default basename
		if(! isset($args['base']))
			$args['base'] = 'basename';

		// Use a default empty config file unless one is specified
		if(! isset($args['config-file']))
			$args['config-file'] = vfsStream::url('root/no-such-file.json');

		$argv = array(self::app);

		foreach($args as $n => $v)
		{
			$argv[] = '--' . $n;
			if($v !== true)
				$argv[] = $v;
		}

		$box = new Boxer;
		$box->silenceStderr();
		$box->readCommandLine($argv);

		return $box;
	}

	public function testReadBoxerConfigReturnsNullByDefault()
	{
		$box = $this->constructBoxer(array(
			'config-file' => Boxer::DEFAULT_CONFIG,
		));
		$conf = $box->readBoxerConfig();

		$this->assertSame(null, $conf);
	}

	public function testReadBoxerConfigReturnsNullForMissingFile()
	{
		$box = $this->constructBoxer(array(
			'config-file' => vfsStream::url('root/no-such-file.json'),
		));
		$conf = $box->readBoxerConfig();

		$this->assertSame(null, $conf);
	}

	public function testReadBoxerConfigThrowsOnInvalidInput()
	{
		$this->setExpectedException('\Vube\VagrantBoxer\Exception\InvalidInputException');

		$box = $this->constructBoxer(array(
			'config-file' => vfsStream::url('root/invalid.json'),
		));
		$conf = $box->readBoxerConfig();
	}

	public function testLoadMetaDataReturnsDefaultWhenNoFile()
	{
		$box = $this->constructBoxer(array(
			'metadata-file' => vfsStream::url('root/no-such-file.json'),
		));
		$box->loadBoxerConfig();
		$r = $box->loadMetaData();

		$this->assertSame(Boxer::METADATA_DEFAULT, $r, "Expected default metadata");
	}

	public function testLoadMetaDataReturnsCustomWithExistingFile()
	{
		$box = $this->constructBoxer(array(
			'metadata-file' => vfsStream::url('root/empty-metadata.json'),
		));
		$box->loadBoxerConfig();
		$r = $box->loadMetaData();

		$this->assertSame(Boxer::METADATA_CUSTOM, $r, "Expected custom metadata");

		$metadata = $box->getMetaData();
		$this->assertArrayHasKey('provider', $metadata);
		$this->assertArrayHasKey('versions', $metadata);
	}

	public function testComputeUrlVariations()
	{
		$box = $this->constructBoxer(array(
			'metadata-file' => vfsStream::url('root/empty-metadata.json'),
		));
		$box->init();
		$metadata = $box->getMetaData();

		$templates = array(
			"" => "",
			"abc" => "abc",
			"{name}" => $metadata['name'],
			"{version}" => "0.0",
			"{provider}" => $metadata['provider'],
			"http://localhost/{name}/{version}/{provider}" => "http://localhost/{$metadata['name']}/0.0/{$metadata['provider']}",
		);

		foreach($templates as $input => $expectedUrl)
		{
			$url = $box->computeUrl($input);
			$this->assertSame($expectedUrl, $url, "unexpected computeUrl(\"$input\") result");
		}
	}

	public function testBumpVersionNumber()
	{
		$box = $this->constructBoxer(array(
			'metadata-file' => vfsStream::url('root/empty-metadata.json'),
		));
		$box->init();

		$meta1 = $box->getMetaData();
		$v1 = $box->getVersion();

		$this->assertSame("0.0", $v1); // control, by default version is 0.0
		$this->assertTrue(count($meta1['versions']) === 0, "initial metadata[versions] is empty");

		$box->bumpVersionNumber();

		$meta2 = $box->getMetaData();
		$v2 = $box->getVersion();

		$this->assertSame("0.1", $v2); // expect minor version to increase
		$this->assertTrue(count($meta2['versions']) === 1, "after update metadata[versions] is populated");
	}

	public function testWriteMetaDataFile()
	{
		// Initially this file does not exist
		$filename = vfsStream::url('root/created-metadata.json');
		$box = $this->constructBoxer(array(
			'metadata-file' => $filename,
		));
		$box->init();
		$box->writeMetaData();

		// The file should now exist
		$this->assertFileExists($filename);
	}

	public function testPersistentMetaData()
	{
		// Initially this file does not exist
		$filename = vfsStream::url('root/created-metadata.json');

		$box = $this->constructBoxer(array(
			'metadata-file' => $filename,
		));
		$box->init();
		$box->writeMetaData();
		$meta1 = $box->getMetaData();

		// The file should now exist
		$this->assertFileExists($filename);

		// Instantiate a new Boxer reading in the metadata we created
		$box = $this->constructBoxer(array(
			'metadata-file' => $filename,
		));
		$box->init();
		$meta2 = $box->getMetaData();

		$this->assertEquals($meta1, $meta2);
	}

	public function testPersistentMetaDataWithVersionBumps()
	{
		// Initially this file does not exist
		$filename = vfsStream::url('root/created-metadata.json');

		$box = $this->constructBoxer(array(
			'metadata-file' => $filename,
		));
		$box->init();
		$box->bumpVersionNumber();
		$box->writeMetaData();
		$meta1 = $box->getMetaData();

		// The file should now exist
		$this->assertFileExists($filename);

		// Instantiate a new Boxer reading in the metadata we created
		$box = $this->constructBoxer(array(
			'metadata-file' => $filename,
		));
		$box->init();
		$meta2 = $box->getMetaData();

		$this->assertEquals($meta1, $meta2);
	}

	public function testExecWithReusedEmptyBox()
	{
		$box = $this->constructBoxer(array(
			'bump-version' => true,
			'keep-package' => true,
			'metadata-file' => 'metadata.json',
			'vagrant-output-file' => 'empty.box', // current directory
		));
		$box->init();
		$box->exec();

		$filename = $box->getBoxFilename();

		$this->assertFileExists($filename);
	}
}

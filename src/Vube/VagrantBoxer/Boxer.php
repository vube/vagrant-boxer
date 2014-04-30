<?php
/**
 * @author Ross Perkins <ross@vubeology.com>
 */

namespace Vube\VagrantBoxer;

use Vube\VagrantBoxer\Exception;
use Vube\VagrantBoxer\Exception\MissingArgumentException;


/**
 * Boxer class
 *
 * @author Ross Perkins <ross@vubeology.com>
 */
class Boxer {

	const DEFAULT_CONFIG = 'default';
	const METADATA_DEFAULT = 0;
	const METADATA_CUSTOM = 1;

	private $stderr;
	private $verbose = false;

	private $majorVersion;
	private $urlTemplate;

	private $name;
	private $version;
	private $url;

	private $provider = 'virtualbox';
	private $updateVersion;
	private $repackage;

	private $pathToVagrant = 'vagrant';

	private $baseName = null;
	private $defaultUrlTemplatePrefix = 'http://localhost/';
	private $defaultUrlTemplateSuffix = '{name}-{version}-{provider}.box';
	private $defaultMajorVersion = 0;
	private $boxerConfigFilename = 'boxer.json'; // in current directory
	private $metadataJsonFilename = 'metadata.json'; // in current directory
	private $vagrantBoxOutputFilename = 'package.box'; // in current directory

	private $metadata;

	public function __construct()
	{
		$this->stderr = STDERR;
		$this->updateVersion = false;
		$this->repackage = true;
	}

	public function getMetaData()
	{
		return $this->metadata;
	}

	public function getVersion()
	{
		return $this->version;
	}

	public function silenceStderr()
	{
		$this->stderr = null;
	}

	public function write()
	{
		if(! $this->verbose)
			return;

		$msg = implode("", func_get_args());
		echo $msg;
	}

	public function writeStderr()
	{
		if($this->stderr === null)
			return;

		$msg = implode("", func_get_args());
		fwrite($this->stderr, $msg);
	}

	/**
	 * @param array $args List of arguments
	 * @param int $i Current index of $args list
	 * @return string
	 * @throws Exception\MissingArgumentException
	 */
	public function getNextArg($args, $i)
	{
		if($i+1 < count($args))
		{
			$value = $args[$i+1];

			// Only return this if it doesn't look like another parameter
			if(substr($value, 0, 2) !== '--')
				return $value;
		}

		throw new MissingArgumentException($args[$i]);
	}

	/**
	 * @param array $args Value of $_SERVER['argv']
	 * @throws Exception
	 */
	public function readCommandLine($args=array())
	{
		array_shift($args); // Remove program name from the front

		$n = count($args);
		for($i=0; $i<$n; $i++)
		{
			$arg = $args[$i];
			switch($arg)
			{
				case '--verbose':
					$this->verbose = true;
					break;

				case '--vagrant':
					$this->pathToVagrant = $this->getNextArg($args, $i);
					break;

				case '--vagrant-output-file':
					$this->vagrantBoxOutputFilename = $this->getNextArg($args, $i);
					$i++;
					break;

				case '--bump-version':
					$this->updateVersion = true;
					break;

				case '--keep-package':
					$this->repackage = false;
					break;

				case '--config-file':
					$this->boxerConfigFilename = $this->getNextArg($args, $i);
					$i++;
					break;

				case '--metadata-file':
					$this->metadataJsonFilename = $this->getNextArg($args, $i);
					$i++;
					break;

				case '--base':
					$this->baseName = $this->getNextArg($args, $i);
					$i++;
					break;

				case '--url':
					$this->defaultUrlTemplatePrefix = '';
					$this->defaultUrlTemplateSuffix = $this->getNextArg($args, $i);
					$i++;
					break;

				case '--url-prefix':
					$this->defaultUrlTemplatePrefix = $this->getNextArg($args, $i);
					$i++;
					break;

				case '--url-suffix':
					$this->defaultUrlTemplateSuffix = $this->getNextArg($args, $i);
					$i++;
					break;

				case '--major-version':
					$this->defaultMajorVersion = $this->getNextArg($args, $i);
					$i++;
					break;

				default:
					throw new Exception("Unrecognized command-line argument: $arg");
					break;
			}
		}
	}

	public function getDefaultMetaData()
	{
		return array(
			'name' => $this->baseName,
			'provider' => $this->provider,
			'versions' => array(),
		);
	}

	public function checkMetaData(&$obj)
	{
		if(! is_array($obj))
			throw new Exception\InvalidInputException("Invalid data read from {$this->metadataJsonFilename}");

		if(! isset($obj['name']))
			$obj['name'] = $this->baseName;

		if(! isset($obj['provider']))
			$obj['provider'] = $this->provider;

		if(! isset($obj['versions']))
			$obj['versions'] = array();
		else if(! is_array($obj['versions']))
			throw new Exception\InvalidInputException("Unexpected data found in {$this->metadataJsonFilename}");
	}

	public function getDefaultBoxerConfig()
	{
		if($this->baseName === null)
			throw new Exception("Must set --base parameter when not using boxer.json configuration");

		return array(
			'vm-name' => $this->baseName,
			'version' => $this->defaultMajorVersion,
			'url-template' => $this->defaultUrlTemplatePrefix . $this->defaultUrlTemplateSuffix,
		);
	}

	public function readBoxerConfig()
	{
		// If they decided to use default values instead of a config, do so
		if($this->boxerConfigFilename === self::DEFAULT_CONFIG)
			return null;

		$json = @file_get_contents($this->boxerConfigFilename);

		if($json === false)
		{
			$cwd = posix_getcwd();
			$this->writeStderr("Warning: No {$this->boxerConfigFilename} (current dir: $cwd), using default");
			return null;
		}

		$obj = json_decode($json, true);

		if(! is_array($obj))
			throw new Exception\InvalidInputException("{$this->boxerConfigFilename} must contain an associative array configuration");

		if(! isset($obj['vm-name']))
			throw new Exception\InvalidInputException("{$this->boxerConfigFilename} does not define a vm-name");

		if(! isset($obj['url-template']))
		{
			if(! isset($obj['download-url-prefix']))
			{
				throw new Exception\InvalidInputException("Neither url-template no download-url-prefix are defined in {$this->boxerConfigFilename}");
			}

			$obj['url-template'] = $obj['download-url-prefix'] . $this->defaultUrlTemplateSuffix;
		}

		return $obj;
	}

	public function loadBoxerConfig()
	{
		$obj = $this->readBoxerConfig();

		if(! $obj)
			$obj = $this->getDefaultBoxerConfig();

		$this->name = $obj['vm-name'];
		$this->majorVersion = isset($obj['version']) ? $obj['version'] : 0;
		$this->urlTemplate = $obj['url-template'];
	}

	public function loadMetaData()
	{
		$json = @file_get_contents($this->metadataJsonFilename);

		if($json !== false)
		{
			$obj = json_decode($json, true);

			$this->checkMetaData($obj);
			$this->metadata = $obj;

			return self::METADATA_CUSTOM;
		}

		$cwd = posix_getcwd();
		$this->writeStderr("Notice: No {$this->metadataJsonFilename} found (current dir: $cwd)");

		$this->metadata = $this->getDefaultMetaData();

		return self::METADATA_DEFAULT;
	}

	public function computeUrl($template=null)
	{
		$url = ($template===null) ? $this->urlTemplate : $template;
		$url = str_replace('{name}', $this->name, $url);
		$url = str_replace('{version}', $this->version, $url);
		$url = str_replace('{provider}', $this->provider, $url);
		return $url;
	}

	public function postConfigure()
	{
		$this->metadata['provider'] = $this->provider;

		if(count($this->metadata['versions']))
		{
			if(! isset($this->metadata['versions'][0]['version']))
				throw new Exception("Unexpected version definition in position 0");

			$this->version = $this->metadata['versions'][0]['version'];
		}
		else
		{
			$this->version = $this->majorVersion . '.0';
		}

		// AFTER computing the version, compute url
		$this->url = $this->computeUrl();

		// In case the name changed, do it
		$this->metadata['name'] = $this->name;
	}

	public function bumpVersionNumber()
	{
		$version = explode(".", $this->version);
		$version[count($version)-1]++;
		$version = implode(".", $version);

		$this->version = $version;

		// We changed the version number, recompute the URL
		$this->url = $this->computeUrl();

		// Add new version to metadata
		$newVersionInfo = array(
			'version' => $version,
			'providers' => array(
				'name' => $this->provider,
				'url' => $this->url,
			),
		);

		// Prepend new version info to metadata versions list
		array_unshift($this->metadata['versions'], $newVersionInfo);
	}

	public function writeMetaData()
	{
		$json = json_encode($this->metadata, JSON_PRETTY_PRINT);

		$fh = fopen($this->metadataJsonFilename, "w");
		if(! $fh)
			throw new Exception("Failed to write {$this->metadataJsonFilename}");

		fwrite($fh, $json);
		fclose($fh);
	}

	public function getBoxFilename()
	{
		return basename($this->url);
	}

	public function package()
	{
		if(substr($this->vagrantBoxOutputFilename,-4) !== ".box")
			throw new Exception("vagrant box output filename must end in .box");

		$boxname = $this->vagrantBoxOutputFilename;
		$package = str_replace(".box", "", $boxname);

		$basename = $this->getBoxFilename();

		if($this->repackage || ! file_exists($boxname))
		{
			@unlink($boxname); // Remove existing file (if any) to prevent vagrant errors

			$command = array(
				$this->pathToVagrant, 'package',
				'--base', escapeshellarg($this->name),
				'--output', escapeshellarg($boxname),
			);

			$command = implode(" ", $command);

			$this->write("EXEC: $command\n");
			passthru($command, $r);

			if($r !== 0)
				throw new Exception("vagrant package failed, exit code=$r from command: $command");

			if(! file_exists($boxname))
				throw new Exception("vagrant package seems to have failed: $boxname does not exist");
		}
		else
		{
			$this->write("Notice: Using existing $boxname due to --keep-package\n");
		}

		$this->write("Opening $boxname\n");
		$zt = new \PharData($boxname);

		$this->write("Decompressing $boxname\n");
		@unlink("$package.tar"); // Must clear the way for the new file
		$t = $zt->decompress('.tar');

		$this->write("Adding metadata.json to $boxname\n");
		$t->addFile($this->metadataJsonFilename, 'metadata.json');

		$this->write("Recompressing $boxname\n");
		@unlink("$package.tar.gz"); // Must clear the way for the new file
		$t->compress(\Phar::GZ);

		// remove temp file package.tar
		unlink("$package.tar");

		// Move new package.box (with metadata.json) to boxname-1.1-virtualbox.box
		rename("$package.tar.gz", $basename);

		$cwd = posix_getcwd();
		$file = realpath($cwd . DIRECTORY_SEPARATOR . $basename);

		$this->write("PACKAGE LOCATION: $file\n");
	}

	public function init($args=array())
	{
		$this->readCommandLine($args);

		// 1) Read/parse boxer.json
		$this->loadBoxerConfig();

		// 2) Read/parse metadata.json
		$this->loadMetaData();

		// 3) Configure some things that require both boxer.json and metadata.json
		$this->postConfigure();
	}

	public function exec()
	{
		// 1) IFF we are updating the metadata
		// 1.1) Bump the version number
		// 1.2) Write the new metadata.json
		if($this->updateVersion)
		{
			$this->bumpVersionNumber();
			$this->writeMetaData();
		}

		// 3) Package up the box
		$this->package();
	}
}
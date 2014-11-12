<?php
/**
 * @author Ross Perkins <ross@vubeology.com>
 */

namespace Vube\VagrantBoxer;


/**
 * MetaData class
 *
 * @author Ross Perkins <ross@vubeology.com>
 */
class MetaData {

	const METADATA_DEFAULT = 0;
	const METADATA_CUSTOM = 1;

	private $data;
	private $defaults;
    private $bIsDefault = true;
	private $modified = false;

	public function __construct($boxerId)
	{
		$this->defaults = array(
			'name' => $boxerId,
			'versions' => array(),
		);
		$this->data = $this->defaults;
	}

	public function dataAsArray()
	{
		return $this->data;
	}

	public function get($field)
	{
		if(isset($this->data[$field]))
			return $this->data[$field];

		throw new Exception("No such metadata field: $field");
	}

	public function set($name, $value)
	{
		if(! isset($this->data[$name]) || $this->data[$name] !== $value)
		{
			$this->modified = true;
			$this->data[$name] = $value;
		}
	}

    public function isDefault()
    {
        return $this->bIsDefault;
    }

    public function isModified()
    {
        return $this->modified;
    }

	public function getNumVersions()
	{
		return count($this->data['versions']);
	}

	public function getActiveVersionNumber()
	{
		if(count($this->data['versions']))
		{
			if(! isset($this->data['versions'][0]['version']))
				throw new Exception("Unexpected version definition in position 0");

			return $this->data['versions'][0]['version'];
		}

		return null;
	}

	public function getVersionNumberIndex($versionNumber)
	{
		$n = count($this->data['versions']);
		for($i=0; $i<$n; $i++)
		{
			if($this->data['versions'][$i]['version'] === $versionNumber)
				return $i;
		}

		// No such version found
		return null;
	}

	private function getProviderIndex(&$providers, $providerName)
	{
		$n = count($providers);
		for($i=0; $i<$n; $i++)
		{
			if($providers[$i]['name'] === $providerName)
				return $i;
		}

		return null;
	}

	public function addVersionProvider($version, $provider)
	{
		$i = $this->getVersionNumberIndex($version);

		if($i === null)
		{
			$this->modified = true;

			$versionData = array(
				'version' => $version,
				'providers' => array($provider),
			);

			// By prepending this to the front of the array, this version becomes
			// the new active version.  This is assumed to be desired.
			array_unshift($this->data['versions'], $versionData);
		}
		else
		{
			// See if we already have this provider for this version, if so
			// then we may need to modify it

			$providers = $this->data['versions'][$i]['providers'];
			$pi = $this->getProviderIndex($providers, $provider['name']);

			// If this provider isn't found, add it
			if($pi === null)
			{
				$this->modified = true;

				$providers[] = $provider;
				$this->data['versions'][$i]['providers'] = $providers;
			}
			// Else the provider was found, update it if it has changed
			else
			{
				$diff = array_diff_assoc($providers[$pi], $provider);
				if(count($diff))
				{
					$this->modified = true;
					$this->data['versions'][$i]['providers'][$pi] = $provider;
				}
			}
		}

        return count($this->data['versions']);
	}

	public function validateJson(&$obj)
	{
		if(! is_array($obj))
			throw new Exception\InvalidInputException("Invalid metadata");

		// If name wasn't explicitly set, save current name
		if(! isset($obj['name']))
			$obj['name'] = $this->get('name');

		// If there are no versions found, initialize a default empty versions array
		if(! isset($obj['versions']))
			$obj['versions'] = array();
		// Else if the versions array isn't an array, exception
		else if(! is_array($obj['versions']))
			throw new Exception\InvalidInputException("Invalid versions setting in metadata");
	}

	public function loadFromFile($file)
	{
		$json = @file_get_contents($file);

		if($json !== false) {
            $obj = json_decode($json, true);

            $this->validateJson($obj);

            $this->modified = false;

            // This could happen if we created the metadata.json with the box named 'foo/bar'
            // but the boxer.json config was later updated to say the new box name is 'foo/rab'.
            // In that case we want to change the metadata['name'] to match the new name that
            // the boxer.json wants us to use.

            if ($obj['name'] !== $this->data['name'])
            {
                $obj['name'] = $this->data['name'];
                $this->modified = true;
            }

			$this->data = $obj;
            $this->bIsDefault = false;

			return self::METADATA_CUSTOM;
		}

		$this->data = $this->defaults;
        $this->bIsDefault = true;
		$this->modified = false;

		return self::METADATA_DEFAULT;
	}

	public function saveToFile($file, $force=false)
	{
		// If the file already exists and we have not modified anything in
		// the metadata, then preserve its mtime; don't overwrite the file.
		if(file_exists($file) && ! $this->modified && ! $force)
			return false;

		// JSON_PRETTY_PRINT was introduced in PHP 5.4
		$flags = PHP_VERSION_ID >= 50400 ? JSON_PRETTY_PRINT : 0;

		$json = json_encode($this->data, $flags);

		$fh = fopen($file, "wb");
		if(! $fh)
			throw new Exception("Failed to write file: $file");

		fwrite($fh, $json);
		fclose($fh);

		return true;
	}
}
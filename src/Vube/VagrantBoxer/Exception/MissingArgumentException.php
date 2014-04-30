<?php
/**
 * @author Ross Perkins <ross@vubeology.com>
 */

namespace Vube\VagrantBoxer\Exception;

use Vube\VagrantBoxer\Exception;


/**
 * MissingArgumentException class
 *
 * @author Ross Perkins <ross@vubeology.com>
 */
class MissingArgumentException extends Exception {

	public function __construct($argument, $code=0, $previous=null)
	{
		parent::__construct("$argument parameter requires an argument", $code, $previous);
	}
} 
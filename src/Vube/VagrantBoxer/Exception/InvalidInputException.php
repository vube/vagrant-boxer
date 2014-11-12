<?php
/**
 * @author Ross Perkins <ross@vubeology.com>
 */

namespace Vube\VagrantBoxer\Exception;

use Vube\VagrantBoxer\Exception;


/**
 * InvalidInputException class
 *
 * @author Ross Perkins <ross@vubeology.com>
 */
class InvalidInputException extends Exception {

    public function __construct($message, $code=0, $previous=null)
    {
        parent::__construct("Invalid Input: $message", $code, $previous);
    }
}

<?php
/**
 * @author Ross Perkins <ross@vubeology.com>
 */

namespace Vube\VagrantBoxer;


/**
 * Exception class
 *
 * @author Ross Perkins <ross@vubeology.com>
 */
class Exception extends \Exception {

    public function __construct($message, $code=0, $previous=null)
    {
        parent::__construct($message, $code, $previous);
    }
}
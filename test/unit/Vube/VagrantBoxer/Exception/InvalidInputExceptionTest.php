<?php
/**
 * @author Ross Perkins <ross@vubeology.com>
 */

namespace unit\Vube\VagrantBoxer;

use Vube\VagrantBoxer\Exception\InvalidInputException;


class InvalidInputExceptionTest extends \PHPUnit_Framework_TestCase {

    public function testException()
    {
        $this->setExpectedException('\\Vube\\VagrantBoxer\\Exception\\InvalidInputException', '', 123);
        throw new InvalidInputException('Yep it worked', 123);
    }
}

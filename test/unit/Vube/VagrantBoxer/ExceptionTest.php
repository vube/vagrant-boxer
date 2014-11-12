<?php
/**
 * @author Ross Perkins <ross@vubeology.com>
 */

namespace unit\Vube\VagrantBoxer;

use Vube\VagrantBoxer\Exception;


class ExceptionTest extends \PHPUnit_Framework_TestCase {

    public function testException()
    {
        $this->setExpectedException('\\Vube\\VagrantBoxer\\Exception', '', 123);
        throw new Exception('Yep it worked', 123);
    }
}

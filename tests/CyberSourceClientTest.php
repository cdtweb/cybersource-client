<?php declare(strict_types=1);

namespace Cdtweb;

use PHPUnit\Framework\TestCase;

/**
 * @coversDefaultClass \Cdtweb\CyberSourceClient
 */
class CyberSourceClientTest extends TestCase
{
    /**
     * @var CyberSourceClient
     */
    protected $client;

    public function setUp()
    {
        $this->client = new CyberSourceClient('', '');
    }

    /**
     * @covers ::checkRequired
     * @expectedException \Exception
     */
    public function testCheckRequired()
    {
        $class = new \ReflectionClass('\Cdtweb\CyberSourceClient');
        $method = $class->getMethod('checkRequired');
        $method->setAccessible(true);
        $method->invokeArgs($this->client, [['creditCard']]);
    }
}

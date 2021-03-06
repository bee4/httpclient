<?php
/**
 * This file is part of the beebot package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Bee4 2015
 * @author    Stephane HULARD <s.hulard@chstudio.fr>
 * @package   Bee4\Test\Transport\Message
 */

namespace Bee4\Test\Transport\Message;

use Bee4\PHPUnit\HttpClientTestCase;

/**
 * AbstractMessage unit test definition
 * @package Bee4\Test\Transport\Message
 */
class AbstractMessageTest extends HttpClientTestCase
{
    /**
     * @var \Bee4\Transport\Message\AbstractMessage
     */
    protected $object;

    /**
     * Test all headers collection manipulation function
     */
    public function testAllHeaders()
    {
        $mock = $this->getMockForAbstractClass(
            '\Bee4\Transport\Message\AbstractMessage'
        );

        $mock->addHeader('Content-Type', 'text/html');
        $this->assertTrue($mock->hasHeader('Content-Type'));
        $this->assertFalse($mock->hasHeader('Content-Length'));

        $this->assertNull($mock->getHeader('Content-Length'));
        $this->assertEquals('text/html', $mock->getHeader('Content-Type'));
        $this->assertEquals('text/html', $mock->getHeader('content-type'));
        $this->assertEquals('text/html', $mock->getHeader('CONTENT-TYPE'));

        $headers = [
            'content-type' => 'application/json',
            'content-length' => 0,
            'x-test: Value'
        ];
        $mock->addHeaders($headers);

        unset($headers[0]);
        $headers['x-test'] = "Value";
        $this->assertEquals($headers, $mock->getHeaders());
        $this->assertEquals("Value", $mock->getHeader('X-Test'));

        $this->assertEquals([
            "content-type: application/json",
            "content-length: 0",
            'x-test: Value'
        ], $mock->getHeaderLines());

        $mock->removeHeader('Content-Type');
        $this->assertNull($mock->getHeader('Content-Type'));
        $mock->removeHeaders();
        $this->assertCount(0, $mock->getHeaders());
    }
}

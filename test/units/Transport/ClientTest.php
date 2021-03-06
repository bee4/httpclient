<?php
/**
 * This file is part of the beebot package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Bee4 2015
 * @author    Stephane HULARD <s.hulard@chstudio.fr>
 * @package   Bee4\Test\Transport
 */

namespace Bee4\Test\Transport;

use Bee4\Events\Adapters\EvenementEventEmitterAdapter;
use Evenement\EventEmitter;
use Bee4\PHPUnit\HttpClientTestCase;
use Bee4\Transport\Client;
use Bee4\Transport\MagicHandler;
use Bee4\Transport\Events\MessageEvent;
use Bee4\Transport\Events\ErrorEvent;

/**
 * Transfer client test
 * @package Bee4\Test\Transport
 */
class ClientTest extends HttpClientTestCase
{
    /**
     * @var Client
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('The curl extension is not available.');
        }

        $this->object = new MagicHandler(new Client(self::getBaseUrl()));
    }

    /**
     * @expectedException \Bee4\Transport\Exception\InvalidArgumentException
     */
    public function testNonStringUrl()
    {
        (new Client())->createRequest('post', new \stdClass());
    }

    /**
     * @expectedException \Bee4\Transport\Exception\InvalidArgumentException
     */
    public function testEmptyUrl()
    {
        (new Client())->createRequest('post', '');
    }

    public function testSend()
    {
        //Check that Post request is nicely mapped
        $request1 = $this->object->get('/index.html');
        $this->assertEquals(self::getBaseUrl().'/index.html', (string)$request1->getUrl());

        $this->assertInstanceOf('\Bee4\Transport\Message\AbstractMessage', $request1);
        $this->assertInstanceOf('\Bee4\Transport\Message\Request\AbstractRequest', $request1);
        $this->assertInstanceOf('\Bee4\Transport\Message\Request\Http\Get', $request1);
        $this->assertInstanceOf('\Bee4\Transport\Message\Response', $request1->send());

        //Check that Post request is nicely mapped
        $request2 = $this->object->post('/index.html');
        $this->assertInstanceOf('\Bee4\Transport\Message\AbstractMessage', $request2);
        $this->assertInstanceOf('\Bee4\Transport\Message\Request\AbstractRequest', $request2);
        $this->assertInstanceOf('\Bee4\Transport\Message\Request\Http\Post', $request2);
        $this->assertInstanceOf('\Bee4\Transport\Message\Response', $request2->send());
    }

    public function testGet()
    {
        $request = $this->object->get('/index.html');
        $response = $request->send();
        $options = $request->getOptions();

        $this->assertEquals('GET', $options->method);
        $this->assertInstanceOf('\Bee4\Transport\Message\Request\Http\Get', $response->getRequest());
    }

    public function testPost()
    {
        $request = $this->object->post('/index.html')->setBody('{"body": "I\'m the body"}}');
        $response = $request->send();
        $options = $request->getOptions();

        $this->assertEquals('POST', $options->method);
        $this->assertEquals('{"body": "I\'m the body"}}', $options->body);
        $this->assertInstanceOf('\Bee4\Transport\Message\Request\Http\Post', $response->getRequest());
    }

    public function testHead()
    {
        $request = $this->object->head('/index.html');
        $response = $request->send();
        $options = $request->getOptions();

        $this->assertFalse($options->body);
        $this->assertEquals('HEAD', $options->method);
        $this->assertInstanceOf('\Bee4\Transport\Message\Request\Http\Head', $response->getRequest());
    }

    public function testDelete()
    {
        $request = $this->object->delete('/index.html');
        $response = $request->send();
        $options = $request->getOptions();

        $this->assertEquals('DELETE', $options->method);
        $this->assertInstanceOf('\Bee4\Transport\Message\Request\Http\Delete', $response->getRequest());
    }

    public function testPutFromString()
    {
        $request = $this->object->put('/index.html');
        $response = $request->send();
        $options = $request->getOptions();

        $this->assertEquals('PUT', $options->method);
        $this->assertEquals('', $options->body);
        $this->assertInstanceOf('\Bee4\Transport\Message\Request\Http\Put', $response->getRequest());
    }

    public function testPutFromStream()
    {
        $stream = tmpfile();
        $size = 200;
        $content = str_repeat('*', $size);
        fwrite($stream, $content);

        $request = $this->object->put('/index.html');
        $request->setBody($stream);
        $response = $request->send();
        $options = $request->getOptions();

        $this->assertEquals('PUT', $options->method);
        $this->assertTrue(is_resource($options->body));
        $this->assertInstanceOf('\Bee4\Transport\Message\Request\Http\Put', $response->getRequest());
    }

    /**
     * @expectedException \Exception
     * @expectedExceptionMessage Yes event triggered
     */
    public function testRegister()
    {
        $dispatcher = new EvenementEventEmitterAdapter(new EventEmitter);
        $dispatcher->on(MessageEvent::REQUEST, function () {
            throw new \Exception("Yes event triggered");
        });

        $this->object->setDispatcher($dispatcher);
        $this->object->get('/index.html')->send();
    }

    public function testInvalidProtocol()
    {
        $this->object = new Client("unmapped://127.0.0.1");

        try {
            $this->object->createRequest('GET')->send();
        } catch (\Exception $error) {
            $this->assertInstanceOf("\\Bee4\\Transport\\Exception\\UnknownProtocolException", $error);
            return;
        }
        $this->fail();
    }

    public function testCurlError()
    {
        $this->expectOutputString('error');
        $this->object = new Client("ftp://127.0.0.1:8888");

        $dispatcher = new EvenementEventEmitterAdapter(new EventEmitter);
        $dispatcher->on(ErrorEvent::ERROR, function () {
            echo "error";
        });
        $this->object->setDispatcher($dispatcher);

        try {
            $this->object->createRequest('GET')->send();
        } catch (\Exception $error) {
            $this->assertInstanceOf("\\Bee4\\Transport\\Exception\\CurlException", $error);
            return;
        }
        $this->fail();
    }
}

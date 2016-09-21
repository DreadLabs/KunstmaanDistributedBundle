<?php

/*
 * This file is part of the `DreadLabs/KunstmaanDistributedBundle` project.
 *
 * (c) https://github.com/DreadLabs/KunstmaanDistributedBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace DreadLabs\KunstmaanDistributedBundle\tests\HttpKernel\HttpCache;

use DreadLabs\KunstmaanDistributedBundle\HttpKernel\HttpCache\PredisStore;
use Predis\Client;
use Predis\ClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PredisStoreTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var ClientInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $client;

    /**
     * @var PredisStore
     */
    protected $store;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var Response
     */
    protected $response;

    public function setUp()
    {
        $this->client = $this->getMockBuilder(Client::class)
            ->setMethods(['connect', 'disconnect', 'set', 'get', 'hSetNx', 'hdel', 'hget', 'del'])
            ->getMock();

        $this->store = new PredisStore($this->client);

        $this->request = Request::create('/');

        $this->response = new Response('Foo? Bar!', 200, []);

        $this->cleanKeys();
    }

    public function tearDown()
    {
        $this->client = null;
        $this->store = null;
        $this->request = null;
        $this->request = null;
        $this->cleanKeys();
    }

    protected function cleanKeys()
    {
        /*
        $client = new Client(array('host' => 'localhost'));
        $client->connect();
        $client->flushall();
        */
    }

    /**
     * @test
     */
    public function emptyCacheAtKey()
    {
        $this->assertEmpty($this->getStoreMetadata('/nothing'));
    }

    protected function getStoreMetadata($key)
    {
        $reflectionStore = new \ReflectionObject($this->store);

        $getMetadata = $reflectionStore->getMethod('getMetadata');
        $getMetadata->setAccessible(true);

        if ($key instanceof Request) {
            $getMetadataKey = $reflectionStore->getMethod('getMetadataKey');
            $getMetadataKey->setAccessible(true);

            $key = $getMetadataKey->invoke($this->store, $key);
        }

        return $getMetadata->invoke($this->store, $key);
    }

    /**
     * @test
     */
    public function existingEntryUnlocking()
    {
        $this->client
            ->expects($this->at(4))
            ->method('get')
            ->with(
                $this->equalTo('hrm'.sha1('http://localhost/test'))
            )
            ->will(
                $this->returnValue(serialize([]))
            );

        $this->storeSimpleEntry();

        $this->store->lock($this->request);

        $this->client
            ->expects($this->any())
            ->method('hdel')
            ->willReturn(1);

        $this->assertTrue($this->store->unlock($this->request));
    }

    protected function storeSimpleEntry($path = null, $headers = [])
    {
        if (null === $path) {
            $path = '/test';
        }
        $this->request = Request::create($path, 'get', [], [], [], $headers);
        $this->response = new Response('test', 200, ['Cache-Control' => 'max-age=420']);

        return $this->store->write($this->request, $this->response);
    }

    /**
     * @test
     */
    public function notExistingEntryUnlocking()
    {
        $this->assertFalse($this->store->unlock($this->request));
    }

    /**
     * @test
     */
    public function purgingRemovesCacheEntriesForKey()
    {
        $this->client
            ->expects($this->at(4))
            ->method('get')
            ->with(
                $this->equalTo('hrm'.sha1('http://localhost/foorequest'))
            )
            ->willReturn(serialize([
                [
                    [],
                    ['x-content-digest' => ['foo']],
                ],
            ]));

        $request = Request::create('/foorequest');
        $this->store->write($request, new Response('fooresponse'));

        // ---

        // InvocationAtIndex begins at 0 for ReflectionObject in ::getStoreMetadata()
        $this->client
            ->expects($this->at(1))
            ->method('get')
            ->with(
                $this->equalTo('hrm'.sha1('http://localhost/foorequest'))
            )
            ->willReturn(serialize([
                [
                    [],
                    ['x-content-digest' => ['foo']],
                ],
            ]));

        $metadata = $this->getStoreMetadata($request);

        $this->assertNotEmpty($metadata);

        // ---

        $this->client
            ->expects($this->at(1))
            ->method('del')
            ->with(
                $this->equalTo('hrm'.sha1('http://localhost/foorequest'))
            )
            ->willReturn(1);

        $this->assertTrue($this->store->purge('/foorequest'));

        // ---

        $this->client
            ->expects($this->at(1))
            ->method('get')
            ->with(
                $this->equalTo('hrm'.sha1('http://localhost/foorequest'))
            )
            ->willReturn(null);

        $this->assertEmpty($this->getStoreMetadata($request));

        // ---

        $this->client
            ->expects($this->at(1))
            ->method('get')
            ->with(
                $this->equalTo('foo')
            )
            ->willReturn(
                'fooresponse'
            );

        $content = $this->loadContentData($metadata[0][1]['x-content-digest'][0]);

        $this->assertNotEmpty($content);

        // ---

        $this->client
            ->method('del')
            ->willReturn(0);

        $this->assertFalse($this->store->purge('/bar'));
    }

    protected function loadContentData($key)
    {
        $reflectedStore = new \ReflectionObject($this->store);
        $reflectedMethod = $reflectedStore->getMethod('load');
        $reflectedMethod->setAccessible(true);

        return $reflectedMethod->invoke($this->store, $key);
    }

    /**
     * @test
     */
    public function storingCacheEntry()
    {
        $this->client
            ->expects($this->any())
            ->method('get')
            ->with(
                $this->equalTo('hrm'.sha1('http://localhost/test'))
            )
            ->willReturn(serialize([
                [
                    [],
                    ['x-content-digest' => ['foo']],
                ],
            ]));

        $cacheKey = $this->storeSimpleEntry();

        $this->assertNotEmpty($this->getStoreMetadata($cacheKey));
    }
}

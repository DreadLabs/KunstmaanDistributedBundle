<?php

/*
 * This file is part of the `DreadLabs/KunstmaanDistributedBundle` project.
 *
 * (c) https://github.com/DreadLabs/KunstmaanDistributedBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace DreadLabs\KunstmaanDistributedBundle\HttpKernel\HttpCache;

use DreadLabs\KunstmaanDistributedBundle\HttpKernel\HttpCache\Metadata\CacheEntry;
use DreadLabs\KunstmaanDistributedBundle\HttpKernel\HttpCache\Metadata\NoMatchException;
use DreadLabs\KunstmaanDistributedBundle\HttpKernel\HttpCache\Metadata\NotFoundException;
use Predis\ClientInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpCache\StoreInterface;

class PredisStore implements StoreInterface
{
    /**
     * @var ClientInterface
     */
    private $client;

    /**
     * @var string
     */
    private $digestKeyPrefix = 'hrd';

    /**
     * @var string
     */
    private $metadataKeyPrefix = 'hrm';

    /**
     * @var string
     */
    private $lockKey = 'hrl';

    /**
     * @var \SplObjectStorage
     */
    private $metadataKeyCache;

    /**
     * @var mixed
     */
    private $recreateCacheMatch;

    /**
     * @var array
     */
    private $recreateHeaders;

    /**
     * @var string
     */
    private $recreateBody;

    /**
     * @var bool
     */
    private $isLockReleased = true;

    /**
     * PredisStore constructor.
     *
     * @param ClientInterface $client
     * @param array           $options
     */
    public function __construct(ClientInterface $client, array $options = [])
    {
        $this->client = $client;

        if (isset($options['digest_key_prefix'])) {
            $this->digestKeyPrefix = (string) $options['digest_key_prefix'];
        }

        if (isset($options['metadata_key_prefix'])) {
            $this->metadataKeyPrefix = (string) $options['metadata_key_prefix'];
        }

        if (isset($options['lock_key'])) {
            $this->lockKey = (string) $options['lock_key'];
        }

        $this->metadataKeyCache = new \SplObjectStorage();
    }

    /**
     * Locates a cached Response for the Request provided.
     *
     * @param Request $request A Request instance
     *
     * @return Response|null A Response instance, or null if no cache entry was found
     */
    public function lookup(Request $request)
    {
        try {
            return $this->recreateResponse($request);
        } catch (NotFoundException $exc) {
            return null;
        } catch (NoMatchException $exc) {
            return null;
        } catch (EmptyBodyException $exc) {
            return null;
        }
    }

    /**
     * Recreate a response object from headers and body.
     *
     * @param Request $request
     *
     * @return Response
     */
    private function recreateResponse(Request $request)
    {
        $this->findMetadataMatch($request);
        $this->setRecreateHeadersAndBody();

        $status = $this->recreateHeaders['X-Status'][0];
        unset($this->recreateHeaders['X-Status']);

        return new Response($this->recreateBody, $status, $this->recreateHeaders);
    }

    /**
     * @param Request $request
     *
     * @throws NoMatchException
     */
    private function findMetadataMatch(Request $request)
    {
        $metadataCacheEntries = $this->getMetadataEntries($request);

        // find a cached entry that matches the request.
        $match = null;

        foreach ($metadataCacheEntries as $metadataCacheEntry) {
            $entry = CacheEntry::createFromRedisResponse($metadataCacheEntry);

            if ($entry->matchesRequest($request)) {
                $match = $entry->toArray();

                break;
            }
        }

        if (null === $match) {
            throw new NoMatchException();
        }

        $this->recreateCacheMatch = $match;
    }

    /**
     * @throws EmptyBodyException
     */
    private function setRecreateHeadersAndBody()
    {
        list($headers) = array_slice($this->recreateCacheMatch, 1, 1);

        $this->recreateHeaders = $headers;
        $recreateDigest = $headers['x-content-digest'][0];
        $this->recreateBody = $this->load($recreateDigest);

        if (!$this->recreateBody) {
            throw new EmptyBodyException();
        }
    }

    /**
     * @param Request $request
     *
     * @throws NotFoundException
     *
     * @return array
     */
    private function getMetadataEntries(Request $request)
    {
        $key = $this->getMetadataKey($request);

        if (!$entries = $this->getMetadata($key)) {
            throw new NotFoundException();
        }

        return $entries;
    }

    /**
     * Returns a cache key for the given Request.
     *
     * @param Request $request A Request instance
     *
     * @return string A key for the given Request
     */
    private function getMetadataKey(Request $request)
    {
        if (isset($this->metadataKeyCache[$request])) {
            return $this->metadataKeyCache[$request];
        }

        return $this->metadataKeyCache[$request] = $this->metadataKeyPrefix.sha1($request->getUri());
    }

    /**
     * Gets all data associated with the given key.
     *
     * Use this method only if you know what you are doing.
     *
     * @param string $key The store key
     *
     * @return array An array of data associated with the key
     */
    private function getMetadata($key)
    {
        if (false === ($metadataCacheEntry = $this->load($key))) {
            return [];
        }

        return unserialize($metadataCacheEntry);
    }

    /**
     * Loads data for the given key.
     *
     * @param string $key The store key
     *
     * @return string The data associated with the key
     */
    private function load($key)
    {
        $this->client->connect();
        $value = $this->client->get($key);
        $this->client->disconnect();

        return $value;
    }

    /**
     * Writes a cache entry to the store for the given Request and Response.
     *
     * Existing entries are read and any that match the response are removed. This
     * method calls write with the new list of cache entries.
     *
     * @param Request  $request  A Request instance
     * @param Response $response A Response instance
     *
     * @return string The key under which the response is stored
     */
    public function write(Request $request, Response $response)
    {
        $this->writeResponseBodyToTheEntityStoreIfThisIsTheOriginalResponse($response);

        $metadataKey = $this->getMetadataKey($request);

        // read existing cache entries, remove non-varying, and add this one to the list
        try {
            $metadataCacheEntries = $this->getMetadataEntries($request);
        } catch (NotFoundException $exc) {
            $metadataCacheEntries = [];
        }

        $updatedMetadataCache = [];

        foreach ($metadataCacheEntries as $metadataCacheEntry) {
            $entry = CacheEntry::createFromRedisResponse($metadataCacheEntry);

            if ($entry->divergesFrom($request, $response)) {
                $updatedMetadataCache[] = $entry->toArray();
            }
        }

        $entry = CacheEntry::createFromFreshHttpExchange($request, $response);
        array_unshift($updatedMetadataCache, $entry->toArray());

        if (false === $this->save($metadataKey, serialize($updatedMetadataCache))) {
            throw new \RuntimeException('Unable to store the metadata.');
        }

        return $metadataKey;
    }

    /**
     * @param Response $response
     *
     * @throws \RuntimeException
     */
    private function writeResponseBodyToTheEntityStoreIfThisIsTheOriginalResponse(Response $response)
    {
        $isOriginalResponse = $response->headers->has('X-Content-Digest');

        if ($isOriginalResponse) {
            return;
        }

        $digest = $this->generateContentDigestKey($response);

        if (false === $this->save($digest, $response->getContent())) {
            throw new \RuntimeException('Unable to store the entity.');
        }

        $response->headers->set('X-Content-Digest', $digest);

        if (!$response->headers->has('Transfer-Encoding')) {
            $response->headers->set('Content-Length', strlen($response->getContent()));
        }
    }

    /**
     * Returns content digest for $response.
     *
     * @param Response $response
     *
     * @return string
     */
    private function generateContentDigestKey(Response $response)
    {
        return $this->digestKeyPrefix.sha1($response->getContent());
    }

    private function save($key, $data)
    {
        $this->client->connect();
        $this->client->set($key, $data);
        $this->client->disconnect();
    }

    /**
     * Invalidates all cache entries that match the request.
     *
     * @param Request $request A Request instance
     */
    public function invalidate(Request $request)
    {
        $isModified = false;
        $newMetadataCache = [];

        $metadataKey = $this->getMetadataKey($request);

        try {
            $metadataCacheEntries = $this->getMetadataEntries($request);
        } catch (NotFoundException $exc) {
            $metadataCacheEntries = [];
        }

        foreach ($metadataCacheEntries as $metadataCacheEntry) {
            $oldEntry = CacheEntry::createFromRedisResponse($metadataCacheEntry);

            $response = $this->recreateResponseFromCacheEntry($oldEntry);

            if (!$response->isFresh()) {
                $newMetadataCache[] = $oldEntry->toArray();
                continue;
            }

            $response->expire();
            $isModified = true;

            $newEntry = CacheEntry::createFromCachedRequest($oldEntry, $response);
            $newMetadataCache[] = $newEntry->toArray();
        }

        if ($isModified) {
            if (false === $this->save($metadataKey, serialize($newMetadataCache))) {
                throw new \RuntimeException('Unable to store the metadata.');
            }
        }
    }

    /**
     * @param \DreadLabs\KunstmaanDistributedBundle\HttpKernel\HttpCache\Metadata\CacheEntry $cacheEntry
     *
     * @return Response
     */
    private function recreateResponseFromCacheEntry(CacheEntry $cacheEntry)
    {
        $rawCacheEntry = $cacheEntry->toArray();
        $this->recreateHeaders = $rawCacheEntry[1];
        $this->recreateBody = null;

        $status = $this->recreateHeaders['X-Status'][0];
        unset($this->recreateHeaders['X-Status']);

        return new Response($this->recreateBody, $status, $this->recreateHeaders);
    }

    /**
     * Locks the cache for a given Request.
     *
     * @param Request $request A Request instance
     *
     * @return bool|string true if the lock is acquired, the path to the current lock otherwise
     */
    public function lock(Request $request)
    {
        $metadataKey = $this->getMetadataKey($request);

        $this->client->connect();
        $result = $this->client->hSetNx($this->lockKey, $metadataKey, 1);
        $this->client->disconnect();

        $this->isLockReleased = false;

        return $result == 1;
    }

    /**
     * Releases the lock for the given Request.
     *
     * @param Request $request A Request instance
     *
     * @return bool False if the lock file does not exist or cannot be unlocked, true otherwise
     */
    public function unlock(Request $request)
    {
        $metadataKey = $this->getMetadataKey($request);

        $this->client->connect();
        $result = $this->client->hdel($this->lockKey, $metadataKey);
        $this->client->disconnect();

        $this->isLockReleased = true;

        return $result == 1;
    }

    /**
     * Returns whether or not a lock exists.
     *
     * @param Request $request A Request instance
     *
     * @return bool true if lock exists, false otherwise
     */
    public function isLocked(Request $request)
    {
        $metadataKey = $this->getMetadataKey($request);

        $this->client->connect();
        $result = $this->client->hget($this->lockKey, $metadataKey);
        $this->client->disconnect();

        return $result == 1;
    }

    /**
     * Purges data for the given URL.
     *
     * @param string $url A URL
     *
     * @return bool true if the URL exists and has been purged, false otherwise
     */
    public function purge($url)
    {
        $metadataKey = $this->getMetadataKey(Request::create($url));

        $this->client->connect();
        $result = $this->client->del($metadataKey);
        $this->client->disconnect();

        return $result == 1;
    }

    /**
     * Cleans up storage.
     */
    public function cleanup()
    {
        if ($this->isLockReleased) {
            return;
        }

        $this->client->connect();
        $result = $this->client->del($this->lockKey);
        $this->client->disconnect();

        if (!$result && false === headers_sent()) {
            // send a 503
            header('HTTP/1.0 503 Service Unavailable');
            header('Retry-After: 10');

            echo 'Cannot unlock store.';
        }
    }
}

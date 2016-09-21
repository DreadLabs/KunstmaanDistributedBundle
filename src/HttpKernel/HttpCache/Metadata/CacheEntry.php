<?php

/*
 * This file is part of the `DreadLabs/KunstmaanDistributedBundle` project.
 *
 * (c) https://github.com/DreadLabs/KunstmaanDistributedBundle/graphs/contributors
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

namespace DreadLabs\KunstmaanDistributedBundle\HttpKernel\HttpCache\Metadata;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class CacheEntry
{
    /**
     * @var array
     */
    private $requestHeaders;

    /**
     * @var array
     */
    private $responseHeaders;

    /**
     * CacheEntry constructor.
     *
     * @param array $requestHeaders
     * @param array $responseHeaders
     */
    private function __construct(array $requestHeaders, array $responseHeaders)
    {
        $this->requestHeaders = $requestHeaders;
        $this->responseHeaders = $responseHeaders;
    }

    /**
     * Creates an instance of a compound redis response.
     *
     * @param array $redisResponse
     *
     * @return CacheEntry
     */
    public static function createFromRedisResponse(array $redisResponse)
    {
        return new self($redisResponse[0], $redisResponse[1]);
    }

    /**
     * Creates an instance for a fresh HTTP exchange.
     *
     * @param Request  $request
     * @param Response $response
     *
     * @return CacheEntry
     */
    public static function createFromFreshHttpExchange(Request $request, Response $response)
    {
        $responseHeaders = $response->headers->all();
        $responseHeaders['X-Status'] = [$response->getStatusCode()];
        unset($responseHeaders['age']);

        return new self($request->headers->all(), $responseHeaders);
    }

    /**
     * @param CacheEntry $entry
     * @param Response   $response
     *
     * @return CacheEntry
     */
    public static function createFromCachedRequest(CacheEntry $entry, Response $response)
    {
        $responseHeaders = $response->headers->all();
        $responseHeaders['X-Status'] = [$response->getStatusCode()];
        unset($responseHeaders['age']);

        return new self($entry->requestHeaders, $responseHeaders);
    }

    /**
     * @param Request  $request
     * @param Response $response
     *
     * @return bool
     */
    public function divergesFrom(Request $request, Response $response)
    {
        $this->resetVaryHeaderIfNotVarying();

        $responseHeaderVaries = $response->headers->get('vary') != $this->responseHeaders['vary'][0];

        $requestHeaderVaries = !$this->areRequestHeaderSetsNonVarying(
            $response->headers->get('vary'),
            $this->requestHeaders,
            $request->headers->all()
        );

        return $responseHeaderVaries || $requestHeaderVaries;
    }

    /**
     * Sets the vary header of the cache entry to an empty string value if it is not set.
     */
    private function resetVaryHeaderIfNotVarying()
    {
        $doesNotVary = !isset($this->responseHeaders['vary'][0]);

        if ($doesNotVary) {
            $this->responseHeaders['vary'] = [''];
        }
    }

    /**
     * Determines whether two Request HTTP header sets are non-varying based on
     * the vary response header value provided.
     *
     * @param string $responseVaryHeader A Response vary header value
     * @param array  $requestHeaderSetA  A Request HTTP header array
     * @param array  $requestHeaderSetB  A Request HTTP header array
     *
     * @return bool true if the two request header sets match, false otherwise
     */
    private function areRequestHeaderSetsNonVarying(
        $responseVaryHeader,
        array $requestHeaderSetA,
        array $requestHeaderSetB
    ) {
        if (empty($responseVaryHeader)) {
            return true;
        }

        $varyHeaderKeys = preg_split('/[\s,]+/', $responseVaryHeader);

        foreach ($varyHeaderKeys as $varyHeaderKey) {
            $varyHeaderKey = strtr(strtolower($varyHeaderKey), '_', '-');

            $requestHeaderA = isset($requestHeaderSetA[$varyHeaderKey]) ? $requestHeaderSetA[$varyHeaderKey] : null;
            $requestHeaderB = isset($requestHeaderSetB[$varyHeaderKey]) ? $requestHeaderSetB[$varyHeaderKey] : null;

            if ($requestHeaderA !== $requestHeaderB) {
                return false;
            }
        }

        return true;
    }

    public function matchesRequest(Request $request)
    {
        $this->resetVaryHeaderIfNotVarying();

        return $this->areRequestHeaderSetsNonVarying(
            $this->responseHeaders['vary'][0],
            $request->headers->all(),
            $this->requestHeaders
        );
    }

    /**
     * @return array
     */
    public function toArray()
    {
        return [$this->requestHeaders, $this->responseHeaders];
    }
}

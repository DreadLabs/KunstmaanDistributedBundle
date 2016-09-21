# Kunstmaan CMS Distributed Bundle

Configures a Kunstmaan Bundles CMS instance for running on distributed, clustered infrastructure.

## What does it do?

  1. Package installations

     In order to operate on distributed infrastructure, Redis was choosen as a backend for various caches:

         snc/redis-bundle
         predis/predis

  2. Compiler passes
     - `kunstmaan_admin.cache`

        Replace filesystem cache with a redis cache.
     - `dreadlabs_kunstmaan_distibuted.cache_page_events.subscriber`

        Registers an EventListener for page cache invalidation.

## How to activate?

Add the following bundles to your `AppKernel`:

    // ...
    new Doctrine\Bundle\DoctrineCacheBundle\DoctrineCacheBundle(),
    new Snc\RedisBundle\SncRedisBundle(),
    new DreadLabs\KunstmaanDistributedBundle\DreadLabsKunstmaanDistributedBundle(),
    // ...

Add the following container parameters to your `parameters.yml.dist`:

    # Set according to the valid clients which may purge the cache
    http_cache_purge_client_ips: [127.0.0.1, ...]
    # Host and port of the proxy server(s)
    http_cache_proxy_servers: [localhost:80]
    # Base-URL of the application
    http_cache_proxy_baseurl: localhost

Add the following configuration keys to your `parameters.yml.dist`:

    redis_host:                    localhost
    redis_db_cache:                1
    redis_db_annotations:          2
    redis_db_http_cache:           3
    redis_db_kunstmaanadmin_cache: 4

Use the Bundle's HttpCache in your `app/AppCache.php`:

    // app/AppCache.php
    // ...
    use DreadLabs\KunstmaanDistributedBundle\HttpCache\HttpCache;
    
    class AppCache extends HttpCache
    {
    }

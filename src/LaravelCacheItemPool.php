<?php

namespace Superbalist\Laravel4PSR6CacheBridge;

use DateTimeImmutable;
use Exception;
use Illuminate\Cache\Repository;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;

class LaravelCacheItemPool implements CacheItemPoolInterface
{
    /**
     * @var Repository
     */
    protected $repository;

    /**
     * @var array
     */
    protected $deferred = [];

    /**
     * @param Repository $repository
     */
    public function __construct(Repository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @return Repository
     */
    public function getRepository()
    {
        return $this->repository;
    }

    /**
     * @return array
     */
    public function getDeferred()
    {
        return $this->deferred;
    }

    public function __destruct()
    {
        $this->commit();
    }

    /**
     * Returns a Cache Item representing the specified key.
     *
     * This method must always return a CacheItemInterface object, even in case of
     * a cache miss. It MUST NOT return null.
     *
     * @param string $key
     *   The key for which to return the corresponding Cache Item.
     *
     * @throws InvalidArgumentException
     *   If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return CacheItemInterface
     *   The corresponding Cache Item.
     */
    public function getItem($key)
    {
        $this->validateKey($key);

        if (isset($this->deferred[$key])) {
            return clone $this->deferred[$key];
        } elseif ($this->repository->has($key)) {
            return new LaravelCacheItem($key, $this->repository->get($key), true);
        } else {
            return new LaravelCacheItem($key);
        }
    }

    /**
     * Returns a traversable set of cache items.
     *
     * @param string[] $keys
     *   An indexed array of keys of items to retrieve.
     *
     * @throws InvalidArgumentException
     *   If any of the keys in $keys are not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return array|\Traversable
     *   A traversable collection of Cache Items keyed by the cache keys of
     *   each item. A Cache item will be returned for each key, even if that
     *   key is not found. However, if no keys are specified then an empty
     *   traversable MUST be returned instead.
     */
    public function getItems(array $keys = [])
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->getItem($key);
        }
        return $values;
    }

    /**
     * Confirms if the cache contains specified cache item.
     *
     * Note: This method MAY avoid retrieving the cached value for performance reasons.
     * This could result in a race condition with CacheItemInterface::get(). To avoid
     * such situation use CacheItemInterface::isHit() instead.
     *
     * @param string $key
     *   The key for which to check existence.
     *
     * @throws InvalidArgumentException
     *   If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return bool
     *   True if item exists in the cache, false otherwise.
     */
    public function hasItem($key)
    {
        $this->validateKey($key);

        if (isset($this->deferred[$key])) {
            $item = $this->deferred[$key]; /** @var LaravelCacheItem $item */
            $expiresAt = $item->getExpiresAt();
            return $expiresAt === null || $expiresAt > new DateTimeImmutable();
        }

        return $this->repository->has($key);
    }

    /**
     * Deletes all items in the pool.
     *
     * @return bool
     *   True if the pool was successfully cleared. False if there was an error.
     */
    public function clear()
    {
        try {
            $this->deferred = [];
            $this->repository->getStore()->flush();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Removes the item from the pool.
     *
     * @param string $key
     *   The key to delete.
     *
     * @throws InvalidArgumentException
     *   If the $key string is not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return bool
     *   True if the item was successfully removed. False if there was an error.
     */
    public function deleteItem($key)
    {
        $this->validateKey($key);

        unset($this->deferred[$key]);

        if (!$this->hasItem($key)) {
            return true;
        }

        return $this->repository->forget($key);
    }

    /**
     * Removes multiple items from the pool.
     *
     * @param string[] $keys
     *   An array of keys that should be removed from the pool.

     *
     * @throws InvalidArgumentException
     *   If any of the keys in $keys are not a legal value a \Psr\Cache\InvalidArgumentException
     *   MUST be thrown.
     *
     * @return bool
     *   True if the items were successfully removed. False if there was an error.
     */
    public function deleteItems(array $keys)
    {
        foreach ($keys as $key) {
            $this->validateKey($key);
        }

        $success = true;

        foreach ($keys as $key) {
            $success = $success && $this->deleteItem($key);
        }

        return $success;
    }

    /**
     * Persists a cache item immediately.
     *
     * @param CacheItemInterface $item
     *   The cache item to save.
     *
     * @return bool
     *   True if the item was successfully persisted. False if there was an error.
     */
    public function save(CacheItemInterface $item)
    {
        $expiresAt = $item instanceof LaravelCacheItem ? $item->getExpiresAt() : null;

        if ($expiresAt === null) {
            try {
                $this->repository->forever($item->getKey(), $item->get());
                return true;
            } catch (Exception $exception) {
                return false;
            }
        }

        $now = new DateTimeImmutable('now', $expiresAt->getTimezone());
        $seconds = $expiresAt->getTimestamp() - $now->getTimestamp();
        $minutes = (int) floor($seconds / 60.0);

        if ($minutes <= 0) {
            // expiry is in the past or in seconds (which is not supported by laravel)
            return false;
        }

        try {
            $this->repository->put($item->getKey(), $item->get(), $minutes);
            return true;
        } catch (Exception $exception) {
            return false;
        }
    }

    /**
     * Sets a cache item to be persisted later.
     *
     * @param CacheItemInterface $item
     *   The cache item to save.
     *
     * @return bool
     *   False if the item could not be queued or if a commit was attempted and failed. True otherwise.
     */
    public function saveDeferred(CacheItemInterface $item)
    {
        $expiresAt = $item instanceof LaravelCacheItem ? $item->getExpiresAt() : null;

        if ($expiresAt && $expiresAt < new DateTimeImmutable()) {
            return false;
        }

        $item = (new LaravelCacheItem($item->getKey(), $item->get(), true))
            ->expiresAt($expiresAt);

        $this->deferred[$item->getKey()] = $item;

        return true;
    }

    /**
     * Persists any deferred cache items.
     *
     * @return bool
     *   True if all not-yet-saved items were successfully saved or there were none. False otherwise.
     */
    public function commit()
    {
        $success = true;

        foreach ($this->deferred as $key => $item) {
            $success = $success && $this->save($item);
        }

        $this->deferred = [];

        return $success;
    }

    /**
     * @param string $key
     *
     * @throws InvalidArgumentException
     */
    private function validateKey($key)
    {
        if (!is_string($key) || preg_match('#[{}\(\)/\\\\@:]#', $key)) {
            throw new InvalidArgumentException();
        }
    }
}

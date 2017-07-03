<?php

namespace Tests;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Illuminate\Cache\Repository;
use Illuminate\Cache\StoreInterface;
use Mockery;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Superbalist\Laravel4PSR6CacheBridge\InvalidArgumentException;
use Superbalist\Laravel4PSR6CacheBridge\LaravelCacheItem;
use Superbalist\Laravel4PSR6CacheBridge\LaravelCacheItemPool;

class LaravelCacheItemPoolTest extends TestCase
{
    public function testGetRepository()
    {
        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);
        $this->assertSame($repository, $pool->getRepository());
    }

    public function testGetWhenKeyIsInvalid()
    {
        $this->expectException(InvalidArgumentException::class);

        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);
        $pool->getItem('@');
    }

    public function testGetItemWhenItemIsHitInDeferred()
    {
        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);

        $item = new LaravelCacheItem('first_name', 'Matthew', true);
        $pool->saveDeferred($item);

        $item2 = $pool->getItem('first_name');
        $this->assertInstanceOf(CacheItemInterface::class, $item2);
        $this->assertNotSame($item, $item2);
        $this->assertEquals('first_name', $item2->getKey());
        $this->assertEquals('Matthew', $item2->get());
        $this->assertTrue($item2->isHit());
    }

    public function testGetItemWhenItemIsHitInRepository()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('first_name')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('first_name')
            ->andReturn('Matthew');
        $pool = new LaravelCacheItemPool($repository);

        $item = $pool->getItem('first_name');
        $this->assertInstanceOf(CacheItemInterface::class, $item);
        $this->assertEquals('first_name', $item->getKey());
        $this->assertEquals('Matthew', $item->get());
        $this->assertTrue($item->isHit());
    }

    public function testGetItemWhenItemIsMiss()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('first_name')
            ->andReturn(false);
        $pool = new LaravelCacheItemPool($repository);

        $item = $pool->getItem('first_name');
        $this->assertInstanceOf(CacheItemInterface::class, $item);
        $this->assertEquals('first_name', $item->getKey());
        $this->assertNull($item->get());
        $this->assertFalse($item->isHit());
    }

    public function testGetItemWhenItemIsHitInDeferredAndRepository()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('first_name')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('first_name')
            ->andReturn('Bob');
        $pool = new LaravelCacheItemPool($repository);

        $item = new LaravelCacheItem('first_name', 'Matthew', true);
        $pool->saveDeferred($item);

        $item2 = $pool->getItem('first_name');
        $this->assertInstanceOf(CacheItemInterface::class, $item2);
        $this->assertNotSame($item, $item2);
        $this->assertEquals('first_name', $item2->getKey());
        $this->assertEquals('Matthew', $item2->get());
        $this->assertTrue($item2->isHit());
    }

    public function testGetItemsWhenAKeyIsInvalid()
    {
        $this->expectException(InvalidArgumentException::class);

        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);
        $pool->getItems(['@', '*']);
    }

    public function testGetItemsWhenKeysAreEmpty()
    {
        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);

        $items = $pool->getItems([]);
        $this->assertInternalType('array', $items);
        $this->assertEmpty($items);
    }

    public function testGetItems()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('first_name')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('first_name')
            ->andReturn('Bob');
        $repository->shouldReceive('has')
            ->once()
            ->with('last_name')
            ->andReturn(true);
        $repository->shouldReceive('get')
            ->once()
            ->with('last_name')
            ->andReturn('Saget');
        $repository->shouldReceive('has')
            ->once()
            ->with('birthday')
            ->andReturn(false);
        $pool = new LaravelCacheItemPool($repository);

        $items = $pool->getItems(['first_name', 'last_name', 'birthday']);
        $this->assertInternalType('array', $items);
        $this->assertEquals(['first_name', 'last_name', 'birthday'], array_keys($items));

        $this->assertEquals('first_name', $items['first_name']->getKey());
        $this->assertEquals('Bob', $items['first_name']->get());
        $this->assertTrue($items['first_name']->isHit());

        $this->assertEquals('last_name', $items['last_name']->getKey());
        $this->assertEquals('Saget', $items['last_name']->get());
        $this->assertTrue($items['last_name']->isHit());

        $this->assertEquals('birthday', $items['birthday']->getKey());
        $this->assertNull($items['birthday']->get());
        $this->assertFalse($items['birthday']->isHit());
    }

    public function testHasItemWhenKeyIsInvalid()
    {
        $this->expectException(InvalidArgumentException::class);

        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);
        $pool->hasItem('@');
    }

    public function testHasItemWhenItemIsHitInDeferred()
    {
        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);

        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $pool->saveDeferred($item);
        $this->assertTrue($pool->hasItem('first_name'));

        $item2 = new LaravelCacheItem('last_name', 'Saget', true);
        $item2->expiresAt(new DateTimeImmutable('+1 minute'));
        $pool->saveDeferred($item2);
        $this->assertTrue($pool->hasItem('last_name'));
    }

    public function testHasItemWhenItemIsInDeferredButHasExpired()
    {
        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);

        $item = new LaravelCacheItem('birthday', 'May 17, 1956', true);
        $item->expiresAt(new DateTimeImmutable('+1 seconds'));
        $pool->saveDeferred($item);
        sleep(2);
        $this->assertFalse($pool->hasItem('birthday'));
    }

    public function testHasItemWhenItemIsHitInRepository()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('first_name')
            ->andReturn(true);
        $pool = new LaravelCacheItemPool($repository);

        $this->assertTrue($pool->hasItem('first_name'));
    }

    public function testHasItemWhenItemIsMiss()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('first_name')
            ->andReturn(false);
        $pool = new LaravelCacheItemPool($repository);

        $this->assertFalse($pool->hasItem('first_name'));
    }

    public function testClear()
    {
        $store = Mockery::mock(StoreInterface::class);
        $store->shouldReceive('flush')
            ->once();
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('getStore')
            ->once()
            ->andReturn($store);
        $pool = new LaravelCacheItemPool($repository);
        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $pool->saveDeferred($item);

        $deferred = $pool->getDeferred();
        $this->assertArrayHasKey('first_name', $deferred);
        $this->assertEquals(1, count($deferred));

        $success = $pool->clear();
        $this->assertTrue($success);
        $this->assertEmpty($pool->getDeferred());
    }

    public function testClearWhenFlushThrowsAnException()
    {
        $store = Mockery::mock(StoreInterface::class);
        $store->shouldReceive('flush')
            ->once()
            ->andThrow(new Exception());
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('getStore')
            ->once()
            ->andReturn($store);
        $pool = new LaravelCacheItemPool($repository);

        $success = $pool->clear();
        $this->assertFalse($success);
    }

    public function testDeleteItemWhenKeyIsInvalid()
    {
        $this->expectException(InvalidArgumentException::class);

        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);
        $pool->deleteItem('@');
    }

    public function testDeleteItemWhenItemIsHitInDeferred()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('last_name')
            ->andReturn(false);
        $pool = new LaravelCacheItemPool($repository);
        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $pool->saveDeferred($item);
        $item2 = new LaravelCacheItem('last_name', 'Saget', true);
        $pool->saveDeferred($item2);

        $deferred = $pool->getDeferred();
        $this->assertArrayHasKey('first_name', $deferred);
        $this->assertArrayHasKey('last_name', $deferred);
        $this->assertEquals(2, count($deferred));

        $success = $pool->deleteItem('last_name');
        $this->assertTrue($success);

        $deferred = $pool->getDeferred();
        $this->assertArrayHasKey('first_name', $deferred);
        $this->assertArrayNotHasKey('last_name', $deferred);
        $this->assertEquals(1, count($deferred));
    }

    public function testDeleteItemWhenItemIsHitInRepository()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('first_name')
            ->andReturn(true);
        $repository->shouldReceive('forget')
            ->once()
            ->with('first_name')
            ->andReturn(true);
        $pool = new LaravelCacheItemPool($repository);

        $success = $pool->deleteItem('first_name');
        $this->assertTrue($success);
    }

    public function testDeleteItemWhenItemIsMiss()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('first_name')
            ->andReturn(false);
        $pool = new LaravelCacheItemPool($repository);

        $success = $pool->deleteItem('first_name');
        $this->assertTrue($success);
    }

    public function testDeleteItemsWhenAKeyIsInvalid()
    {
        $this->expectException(InvalidArgumentException::class);

        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);
        $pool->deleteItems(['@', '*']);
    }

    public function testDeleteItems()
    {
        // when both keys are hits - should be a success
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('first_name')
            ->andReturn(true);
        $repository->shouldReceive('forget')
            ->once()
            ->with('first_name')
            ->andReturn(true);
        $repository->shouldReceive('has')
            ->once()
            ->with('last_name')
            ->andReturn(true);
        $repository->shouldReceive('forget')
            ->once()
            ->with('last_name')
            ->andReturn(true);

        $pool = new LaravelCacheItemPool($repository);

        $success = $pool->deleteItems(['first_name', 'last_name']);
        $this->assertTrue($success);

        // when only 1 of the 2 keys is a hit - should still be a success
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('first_name')
            ->andReturn(true);
        $repository->shouldReceive('forget')
            ->once()
            ->with('first_name')
            ->andReturn(true);
        $repository->shouldReceive('has')
            ->once()
            ->with('last_name')
            ->andReturn(false);

        $pool = new LaravelCacheItemPool($repository);

        $success = $pool->deleteItems(['first_name', 'last_name']);
        $this->assertTrue($success);

        // when 1 of the deletes fails, should be a failure
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('has')
            ->once()
            ->with('first_name')
            ->andReturn(true);
        $repository->shouldReceive('forget')
            ->once()
            ->with('first_name')
            ->andReturn(true);
        $repository->shouldReceive('has')
            ->once()
            ->with('last_name')
            ->andReturn(true);
        $repository->shouldReceive('forget')
            ->once()
            ->with('last_name')
            ->andReturn(false);

        $pool = new LaravelCacheItemPool($repository);

        $success = $pool->deleteItems(['first_name', 'last_name']);
        $this->assertFalse($success);
    }

    public function testSaveWhenItemDoesNotExpire()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('forever')
            ->once()
            ->withArgs(['first_name', 'Bob']);
        $pool = new LaravelCacheItemPool($repository);

        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $success = $pool->save($item);
        $this->assertTrue($success);
    }

    public function testSaveWhenItemDoesNotExpireAndCacheStoreThrowsAnException()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('forever')
            ->once()
            ->withArgs(['first_name', 'Bob'])
            ->andThrow(new Exception());
        $pool = new LaravelCacheItemPool($repository);

        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $success = $pool->save($item);
        $this->assertFalse($success);
    }

    public function testSaveWhenExpiryIsInThePast()
    {
        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);

        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $item->expiresAt(new DateTimeImmutable('-3 hours'));
        $success = $pool->save($item);
        $this->assertFalse($success);
    }

    public function testSaveWhenExpiryIsInSeconds()
    {
        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);

        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $item->expiresAt(new DateTimeImmutable('+45 seconds'));
        $success = $pool->save($item);
        $this->assertFalse($success);
    }

    public function testSave()
    {
        // with relative ttl
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('put')
            ->once()
            ->withArgs(['first_name', 'Bob', 60]);
        $pool = new LaravelCacheItemPool($repository);

        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $item->expiresAt(new DateTimeImmutable('+1 hour'));
        $success = $pool->save($item);
        $this->assertTrue($success);

        // with absolute ttl
        $now = new DateTimeImmutable();
        $expiresAt = new DateTimeImmutable('2057-06-30 14:30:00', new DateTimeZone('Africa/Johannesburg'));
        $seconds = $expiresAt->getTimestamp() - $now->getTimestamp();
        $minutes = (int) floor($seconds / 60.0);

        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('put')
            ->once()
            ->withArgs(['first_name', 'Bob', $minutes]);
        $pool = new LaravelCacheItemPool($repository);

        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $item->expiresAt($expiresAt);
        $success = $pool->save($item);
        $this->assertTrue($success);
    }

    public function testSaveWhenCacheStoreThrowsException()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('put')
            ->once()
            ->withArgs(['first_name', 'Bob', 60])
            ->andThrow(new Exception());
        $pool = new LaravelCacheItemPool($repository);

        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $item->expiresAt(new DateTimeImmutable('+1 hour'));
        $success = $pool->save($item);
        $this->assertFalse($success);
    }

    public function testSaveDeferred()
    {
        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);

        $this->assertEmpty($pool->getDeferred());

        // save first item with key 'first_name'
        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $expiresAt = new DateTimeImmutable('2057-06-30 14:30:00', new DateTimeZone('Africa/Johannesburg'));
        $item->expiresAt($expiresAt);
        $success = $pool->saveDeferred($item);
        $this->assertTrue($success);

        $items = $pool->getDeferred();
        $this->assertEquals(1, count($items));
        $this->assertArrayHasKey('first_name', $items);

        $item2 = $items['first_name']; /** @var LaravelCacheItem $item2 */
        $this->assertNotSame($item, $item2);
        $this->assertInstanceOf(LaravelCacheItem::class, $item2);
        $this->assertEquals('first_name', $item2->getKey());
        $this->assertEquals('Bob', $item2->get());
        $this->assertTrue($item2->isHit());
        $this->assertEquals($expiresAt, $item2->getExpiresAt());

        // override the 'first_name' key
        $item = new LaravelCacheItem('first_name', 'Matthew', true);
        $expiresAt = new DateTimeImmutable('2057-03-30 17:45:00', new DateTimeZone('Africa/Johannesburg'));
        $item->expiresAt($expiresAt);
        $success = $pool->saveDeferred($item);
        $this->assertTrue($success);

        $items = $pool->getDeferred();
        $this->assertEquals(1, count($items));
        $this->assertArrayHasKey('first_name', $items);

        $item2 = $items['first_name']; /** @var LaravelCacheItem $item2 */
        $this->assertNotSame($item, $item2);
        $this->assertInstanceOf(LaravelCacheItem::class, $item2);
        $this->assertEquals('first_name', $item2->getKey());
        $this->assertEquals('Matthew', $item2->get());
        $this->assertTrue($item2->isHit());
        $this->assertEquals($expiresAt, $item2->getExpiresAt());

        // add a new 'last_name' key without an expiry date
        $item = new LaravelCacheItem('last_name', 'Saget', true);
        $success = $pool->saveDeferred($item);
        $this->assertTrue($success);

        $items = $pool->getDeferred();
        $this->assertEquals(2, count($items));
        $this->assertArrayHasKey('first_name', $items);
        $this->assertArrayHasKey('last_name', $items);

        $item2 = $items['last_name']; /** @var LaravelCacheItem $item2 */
        $this->assertNotSame($item, $item2);
        $this->assertInstanceOf(LaravelCacheItem::class, $item2);
        $this->assertEquals('last_name', $item2->getKey());
        $this->assertEquals('Saget', $item2->get());
        $this->assertTrue($item2->isHit());
        $this->assertNull($item2->getExpiresAt());
    }

    public function testSaveDeferredWhenItemDoesNotCarryExpiryDate()
    {
        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);

        $this->assertEmpty($pool->getDeferred());

        $item = Mockery::mock(CacheItemInterface::class);
        $item->shouldReceive('getKey')
            ->andReturn('birthday');
        $item->shouldReceive('get')
            ->andReturn('May 17, 1956');
        $success = $pool->saveDeferred($item);
        $this->assertTrue($success);

        $items = $pool->getDeferred();
        $this->assertEquals(1, count($items));
        $this->assertArrayHasKey('birthday', $items);

        $item2 = $items['birthday']; /** @var LaravelCacheItem $item2 */
        $this->assertNotSame($item, $item2);
        $this->assertInstanceOf(LaravelCacheItem::class, $item2);
        $this->assertEquals('birthday', $item2->getKey());
        $this->assertEquals('May 17, 1956', $item2->get());
        $this->assertTrue($item2->isHit());
        $this->assertNull($item2->getExpiresAt());
    }

    public function testSaveDeferredWhenItemHasExpiryDateInThePast()
    {
        $repository = Mockery::mock(Repository::class);
        $pool = new LaravelCacheItemPool($repository);

        $this->assertEmpty($pool->getDeferred());

        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $expiresAt = new DateTimeImmutable('-1 day', new DateTimeZone('Africa/Johannesburg'));
        $item->expiresAt($expiresAt);
        $success = $pool->saveDeferred($item);
        $this->assertFalse($success);

        $items = $pool->getDeferred();
        $this->assertEmpty($items);
    }

    public function testCommit()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('forever')
            ->once()
            ->withArgs(['first_name', 'Bob']);
        $repository->shouldReceive('forever')
            ->once()
            ->withArgs(['last_name', 'Saget']);
        $repository->shouldReceive('put')
            ->once()
            ->withArgs(['birthday', 'May 17, 1956', 60]);
        $pool = new LaravelCacheItemPool($repository);

        $this->assertEmpty($pool->getDeferred());

        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $pool->saveDeferred($item);
        $this->assertEquals(1, count($pool->getDeferred()));

        $item2 = new LaravelCacheItem('last_name', 'Saget', true);
        $pool->saveDeferred($item2);
        $this->assertEquals(2, count($pool->getDeferred()));

        $success = $pool->commit();
        $this->assertTrue($success);

        $this->assertEmpty($pool->getDeferred());

        $item3 = new LaravelCacheItem('birthday', 'May 17, 1956', true);
        $item3->expiresAt(new DateTimeImmutable('+1 hour'));
        $pool->saveDeferred($item3);
        $this->assertEquals(1, count($pool->getDeferred()));

        $success = $pool->commit();
        $this->assertTrue($success);
    }

    public function testCommitWhenAnItemFailsToSave()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('forever')
            ->once()
            ->withArgs(['first_name', 'Bob']);
        $repository->shouldReceive('forever')
            ->once()
            ->withArgs(['last_name', 'Saget'])
            ->andThrow(new Exception());
        $pool = new LaravelCacheItemPool($repository);

        $this->assertEmpty($pool->getDeferred());

        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $pool->saveDeferred($item);
        $this->assertEquals(1, count($pool->getDeferred()));

        $item2 = new LaravelCacheItem('last_name', 'Saget', true);
        $pool->saveDeferred($item2);
        $this->assertEquals(2, count($pool->getDeferred()));

        $success = $pool->commit();
        $this->assertFalse($success);

        $this->assertEmpty($pool->getDeferred());
    }

    public function testCommitIsCalledWhenPoolIsDestructed()
    {
        $repository = Mockery::mock(Repository::class);
        $repository->shouldReceive('forever')
            ->once()
            ->withArgs(['first_name', 'Bob']);
        $repository->shouldReceive('forever')
            ->once()
            ->withArgs(['last_name', 'Saget']);
        $pool = new LaravelCacheItemPool($repository);

        $this->assertEmpty($pool->getDeferred());

        $item = new LaravelCacheItem('first_name', 'Bob', true);
        $pool->saveDeferred($item);
        $this->assertEquals(1, count($pool->getDeferred()));

        $item2 = new LaravelCacheItem('last_name', 'Saget', true);
        $pool->saveDeferred($item2);
        $this->assertEquals(2, count($pool->getDeferred()));

        $pool = null;
    }
}

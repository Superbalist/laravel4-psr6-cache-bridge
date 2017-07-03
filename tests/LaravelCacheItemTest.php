<?php

namespace Tests;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Superbalist\Laravel4PSR6CacheBridge\LaravelCacheItem;

class LaravelCacheItemTest extends TestCase
{
    public function testGetKey()
    {
        $item = new LaravelCacheItem('first_name');
        $this->assertEquals('first_name', $item->getKey());
    }

    public function testGet()
    {
        $item = new LaravelCacheItem('first_name');
        $this->assertNull($item->get());

        $item = new LaravelCacheItem('first_name', 'Matthew', true);
        $this->assertEquals('Matthew', $item->get());
    }

    public function testGetWhenItemIsCacheMissReturnsNull()
    {
        $item = new LaravelCacheItem('first_name', 'Matthew');
        $this->assertNull($item->get());
    }

    public function testIsHit()
    {
        $item = new LaravelCacheItem('first_name');
        $this->assertFalse($item->isHit());

        $item = new LaravelCacheItem('first_name', 'Matthew', true);
        $this->assertTrue($item->isHit());
    }

    public function testSet()
    {
        $item = new LaravelCacheItem('first_name', 'Matthew', true);
        $this->assertEquals('Matthew', $item->get());

        $return = $item->set('Bob');
        $this->assertEquals('Bob', $item->get());
        $this->assertSame($item, $return);
    }

    public function testExpiresAt()
    {
        $item = new LaravelCacheItem('first_name', 'Matthew', true);

        $dt = new DateTimeImmutable('2017-06-30 14:30:00', new DateTimeZone('Africa/Johannesburg'));
        $return = $item->expiresAt($dt);
        $this->assertSame($dt, $item->getExpiresAt());
        $this->assertSame($item, $return);

        $dt = new DateTime('2017-06-30 14:30:00', new DateTimeZone('Africa/Johannesburg'));
        $item->expiresAt($dt);
        $dt2 = $item->getExpiresAt();
        $this->assertInstanceOf(DateTimeImmutable::class, $dt2);
        $this->assertEquals($dt, $dt2);

        $item->expiresAt(null);
        $this->assertNull($item->getExpiresAt());
    }

    public function testExpiresAfter()
    {
        $item = new LaravelCacheItem('first_name', 'Matthew', true);

        $return = $item->expiresAfter(60);
        $this->assertSame($item, $return);
        $this->assertEquals(
            (new DateTimeImmutable('+1 minute'))->format('Y-m-d H:i:s'),
            $item->getExpiresAt()->format('Y-m-d H:i:s')
        );

        $item->expiresAfter(new DateInterval('PT5M'));
        $this->assertEquals(
            (new DateTimeImmutable('+5 minutes'))->format('Y-m-d H:i:s'),
            $item->getExpiresAt()->format('Y-m-d H:i:s')
        );

        $item->expiresAfter(null);
        $this->assertNull($item->getExpiresAt());
    }
}

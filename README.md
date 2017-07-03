# laravel4-psr6-cache-bridge

A PSR6 cache implementation for Laravel 4.

[![Author](http://img.shields.io/badge/author-@superbalist-blue.svg?style=flat-square)](https://twitter.com/superbalist)
[![Build Status](https://img.shields.io/travis/Superbalist/laravel4-psr6-cache-bridge/master.svg?style=flat-square)](https://travis-ci.org/Superbalist/laravel4-psr6-cache-bridge)
[![StyleCI](https://styleci.io/repos/67781155/shield?branch=master)](https://styleci.io/repos/67781155)
[![Software License](https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square)](LICENSE)
[![Packagist Version](https://img.shields.io/packagist/v/superbalist/laravel4-psr6-cache-bridge.svg?style=flat-square)](https://packagist.org/packages/superbalist/laravel4-psr6-cache-bridge)
[![Total Downloads](https://img.shields.io/packagist/dt/superbalist/laravel4-psr6-cache-bridge.svg?style=flat-square)](https://packagist.org/packages/superbalist/laravel4-psr6-cache-bridge)

This library is based off the Laravel 5 implementation by [madewithlove/illuminate-psr-cache-bridge](https://github.com/madewithlove/illuminate-psr-cache-bridge).

## Installation

```bash
composer require superbalist/laravel4-psr6-cache-bridge
```

Register the service provider in app.php
```php
'providers' => [
    // ...
    'Superbalist\Laravel4PSR6CacheBridge\ServiceProvider'
]
```

## Usage

You can now start using or injecting the `CacheItemPoolInterface` implementation for libraries which expect
a PSR6 cache implementation.

```php
use DateTimeImmutable;
use Psr\Cache\CacheItemPoolInterface;
use Superbalist\Laravel4PSR6CacheBridge\LaravelCacheItem::class;
use Superbalist\Laravel4PSR6CacheBridge\LaravelCacheItemPool::class;

$pool = app(CacheItemPoolInterface::class);
// or
$pool = app(LaravelCacheItemPool::class);

// save an item with an absolute ttl
$item = new LaravelCacheItem('first_name', 'Bob', true);
$item->expiresAt(new DateTimeImmutable('2017-06-30 14:30:00'));
$pool->save($item);

// save an item with a relative ttl
$item = new LaravelCacheItem('first_name', 'Bob', true);
$item->expiresAfter(60);
$pool->save($item);

// save an item permanently
$item = new LaravelCacheItem('first_name', 'Bob', true);
$pool->save($item);

// retrieve an item
$item = $pool->get('first_name');

// working with an item
var_dump($item->getKey());
var_dump($item->get());
var_dump($item->isHit());

// retrieve one or many items
$items = $pool->getItems(['first_name']);
var_dump($items['first_name']);

// check if an item exists in cache
var_dump($pool->hasItem('first_name'));

// wipe out all items
$pool->clear();

// delete an item
$pool->deleteItem('first_name');

// delete one or many items
$pool->deleteItems(['first_name']);

// save a deferred item
$item = new LaravelCacheItem('first_name', 'Bob', true);
$item->expiresAt(new DateTimeImmutable('+1 hour'));
$pool->saveDeferred($item);

// commit all deferred items
$pool->commit();
```

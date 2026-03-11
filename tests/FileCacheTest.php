<?php

declare(strict_types=1);

namespace LPhenom\Cache\Tests;

use LPhenom\Cache\Driver\FileCache;
use LPhenom\Cache\Exception\CacheException;
use LPhenom\Storage\StorageInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class FileCacheTest extends TestCase
{
    /** @var StorageInterface&MockObject */
    private StorageInterface $storage;
    private FileCache $cache;

    protected function setUp(): void
    {
        $this->storage = $this->createMock(StorageInterface::class);
        $this->cache   = new FileCache($this->storage);
    }

    // ─── get ──────────────────────────────────────────────────────────────────

    public function testGetReturnsNullWhenFileDoesNotExist(): void
    {
        $this->storage->method('exists')->willReturn(false);
        self::assertNull($this->cache->get('key'));
    }

    public function testGetReturnsValueForValidEntry(): void
    {
        $this->storage->method('exists')->willReturn(true);
        $this->storage->method('get')->willReturn("0\nhello");

        self::assertSame('hello', $this->cache->get('key'));
    }

    public function testGetReturnsNullForExpiredEntry(): void
    {
        $expired = time() - 10;
        $this->storage->method('exists')->willReturn(true);
        $this->storage->method('get')->willReturn($expired . "\nstale");

        self::assertNull($this->cache->get('key'));
    }

    public function testGetReturnsValueWhenTtlNotYetExpired(): void
    {
        $future = time() + 3600;
        $this->storage->method('exists')->willReturn(true);
        $this->storage->method('get')->willReturn($future . "\nfresh");

        self::assertSame('fresh', $this->cache->get('key'));
    }

    public function testGetReturnsNullWhenStorageThrows(): void
    {
        $this->storage->method('exists')->willReturn(true);
        $this->storage->method('get')->willThrowException(new \RuntimeException('disk error'));

        self::assertNull($this->cache->get('key'));
    }

    public function testGetReturnsNullForMalformedEntry(): void
    {
        // No newline — can't parse
        $this->storage->method('exists')->willReturn(true);
        $this->storage->method('get')->willReturn('no-newline-here');

        self::assertNull($this->cache->get('key'));
    }

    // ─── set ──────────────────────────────────────────────────────────────────

    public function testSetCallsStoragePut(): void
    {
        $this->storage->expects(self::once())
            ->method('put')
            ->with(
                self::matchesRegularExpression('/\.cache$/'),
                self::stringStartsWith('0' . "\n")
            );

        $this->cache->set('mykey', 'myvalue');
    }

    public function testSetWithTtlWritesExpiry(): void
    {
        $before = time();
        $this->storage->expects(self::once())
            ->method('put')
            ->with(
                self::anything(),
                self::callback(function (string $content) use ($before): bool {
                    [$expLine] = explode("\n", $content, 2);
                    $exp = (int) $expLine;
                    return $exp >= $before + 60 && $exp <= $before + 62;
                })
            );

        $this->cache->set('key', 'val', 60);
    }

    public function testSetThrowsCacheExceptionWhenStorageFails(): void
    {
        $this->storage->method('put')
            ->willThrowException(new \RuntimeException('write fail'));

        $this->expectException(CacheException::class);
        $this->cache->set('key', 'val');
    }

    // ─── delete ───────────────────────────────────────────────────────────────

    public function testDeleteCallsStorageDelete(): void
    {
        $this->storage->method('exists')->willReturn(true);
        $this->storage->expects(self::once())->method('delete');

        $this->cache->delete('key');
    }

    public function testDeleteSkipsWhenFileDoesNotExist(): void
    {
        $this->storage->method('exists')->willReturn(false);
        $this->storage->expects(self::never())->method('delete');

        $this->cache->delete('key');
    }

    public function testDeleteThrowsCacheExceptionWhenStorageFails(): void
    {
        $this->storage->method('exists')->willReturn(true);
        $this->storage->method('delete')
            ->willThrowException(new \RuntimeException('unlink fail'));

        $this->expectException(CacheException::class);
        $this->cache->delete('key');
    }

    // ─── has ──────────────────────────────────────────────────────────────────

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->storage->method('exists')->willReturn(true);
        $this->storage->method('get')->willReturn("0\nsome");

        self::assertTrue($this->cache->has('key'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->storage->method('exists')->willReturn(false);
        self::assertFalse($this->cache->has('key'));
    }

    // ─── increment ────────────────────────────────────────────────────────────

    public function testIncrementCreatesNewKeyWithByValue(): void
    {
        $this->storage->method('exists')->willReturn(false);
        $this->storage->expects(self::once())
            ->method('put')
            ->with(
                self::anything(),
                self::callback(function (string $c): bool {
                    [, $val] = explode("\n", $c, 2);
                    return $val === '5';
                })
            );

        $result = $this->cache->increment('new', 5, 0);
        self::assertSame(5, $result);
    }

    public function testIncrementAddsToExistingValue(): void
    {
        $this->storage->method('exists')->willReturn(true);
        $this->storage->method('get')->willReturn("0\n10");

        $written = null;
        $this->storage->method('put')->willReturnCallback(
            function (string $path, string $content) use (&$written): void {
                $written = $content;
            }
        );

        $result = $this->cache->increment('ctr', 3);
        self::assertSame(13, $result);
    }

    public function testIncrementTreatsExpiredEntryAsNew(): void
    {
        $past = time() - 100;
        $this->storage->method('exists')->willReturn(true);
        $this->storage->method('get')->willReturn($past . "\n99");

        $written = null;
        $this->storage->method('put')->willReturnCallback(
            function (string $path, string $content) use (&$written): void {
                $written = $content;
            }
        );

        $result = $this->cache->increment('ctr', 7, 60);
        self::assertSame(7, $result);
    }

    public function testIncrementThrowsWhenWriteFails(): void
    {
        $this->storage->method('exists')->willReturn(false);
        $this->storage->method('put')
            ->willThrowException(new \RuntimeException('disk full'));

        $this->expectException(CacheException::class);
        $this->cache->increment('ctr', 1);
    }
}

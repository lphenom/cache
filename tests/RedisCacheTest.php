<?php

declare(strict_types=1);

namespace LPhenom\Cache\Tests;

use LPhenom\Cache\Driver\RedisCache;
use LPhenom\Cache\Exception\CacheException;
use LPhenom\Redis\Client\RedisClientInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RedisCacheTest extends TestCase
{
    /** @var RedisClientInterface&MockObject */
    private RedisClientInterface $redis;
    private RedisCache $cache;

    protected function setUp(): void
    {
        $this->redis = $this->createMock(RedisClientInterface::class);
        $this->cache = new RedisCache($this->redis);
    }

    // ─── get ──────────────────────────────────────────────────────────────────

    public function testGetReturnsNullWhenKeyMissing(): void
    {
        $this->redis->method('get')->willReturn(null);
        self::assertNull($this->cache->get('key'));
    }

    public function testGetReturnsValue(): void
    {
        $this->redis->method('get')->willReturn('hello');
        self::assertSame('hello', $this->cache->get('key'));
    }

    public function testGetThrowsCacheExceptionWhenRedisFails(): void
    {
        $this->redis->method('get')
            ->willThrowException(new \RuntimeException('connection refused'));

        $this->expectException(CacheException::class);
        $this->cache->get('key');
    }

    // ─── set ──────────────────────────────────────────────────────────────────

    public function testSetCallsRedisSet(): void
    {
        $this->redis->expects(self::once())
            ->method('set')
            ->with(self::anything(), 'value', 0);

        $this->cache->set('key', 'value');
    }

    public function testSetWithTtlPassesTtlToRedis(): void
    {
        $this->redis->expects(self::once())
            ->method('set')
            ->with(self::anything(), 'v', 120);

        $this->cache->set('key', 'v', 120);
    }

    public function testSetThrowsCacheExceptionWhenRedisFails(): void
    {
        $this->redis->method('set')
            ->willThrowException(new \RuntimeException('write error'));

        $this->expectException(CacheException::class);
        $this->cache->set('key', 'val');
    }

    // ─── delete ───────────────────────────────────────────────────────────────

    public function testDeleteCallsRedisDel(): void
    {
        $this->redis->expects(self::once())->method('del');
        $this->cache->delete('key');
    }

    public function testDeleteThrowsCacheExceptionWhenRedisFails(): void
    {
        $this->redis->method('del')
            ->willThrowException(new \RuntimeException('del error'));

        $this->expectException(CacheException::class);
        $this->cache->delete('key');
    }

    // ─── has ──────────────────────────────────────────────────────────────────

    public function testHasReturnsTrueWhenKeyExists(): void
    {
        $this->redis->method('exists')->willReturn(true);
        self::assertTrue($this->cache->has('key'));
    }

    public function testHasReturnsFalseWhenKeyMissing(): void
    {
        $this->redis->method('exists')->willReturn(false);
        self::assertFalse($this->cache->has('key'));
    }

    public function testHasThrowsCacheExceptionWhenRedisFails(): void
    {
        $this->redis->method('exists')
            ->willThrowException(new \RuntimeException('exists error'));

        $this->expectException(CacheException::class);
        $this->cache->has('key');
    }

    // ─── increment ────────────────────────────────────────────────────────────

    public function testIncrementByOneUsesAtomicIncr(): void
    {
        $this->redis->expects(self::once())
            ->method('incr')
            ->willReturn(1);

        $result = $this->cache->increment('cnt', 1);
        self::assertSame(1, $result);
    }

    public function testIncrementByOneSetsExpireOnFirstIncr(): void
    {
        $this->redis->method('incr')->willReturn(1); // new key
        $this->redis->expects(self::once())
            ->method('expire')
            ->with(self::anything(), 60);

        $this->cache->increment('cnt', 1, 60);
    }

    public function testIncrementByOneDoesNotSetExpireOnSubsequentIncr(): void
    {
        $this->redis->method('incr')->willReturn(2); // existing key
        $this->redis->expects(self::never())->method('expire');

        $this->cache->increment('cnt', 1, 60);
    }

    public function testIncrementByMoreThanOneCreatesNewKeyWhenMissing(): void
    {
        $this->redis->method('get')->willReturn(null);
        $this->redis->expects(self::once())
            ->method('set')
            ->with(self::anything(), '5', 0);

        $result = $this->cache->increment('cnt', 5);
        self::assertSame(5, $result);
    }

    public function testIncrementByMoreThanOneAddsToExistingValue(): void
    {
        $this->redis->method('get')->willReturn('10');
        $this->redis->expects(self::once())
            ->method('set')
            ->with(self::anything(), '13', 0);

        $result = $this->cache->increment('cnt', 3);
        self::assertSame(13, $result);
    }

    public function testIncrementThrowsCacheExceptionWhenIncrFails(): void
    {
        $this->redis->method('incr')
            ->willThrowException(new \RuntimeException('incr error'));

        $this->expectException(CacheException::class);
        $this->cache->increment('cnt', 1);
    }

    public function testIncrementThrowsCacheExceptionWhenSetFailsForNewKey(): void
    {
        $this->redis->method('get')->willReturn(null);
        $this->redis->method('set')
            ->willThrowException(new \RuntimeException('set error'));

        $this->expectException(CacheException::class);
        $this->cache->increment('cnt', 5);
    }
}

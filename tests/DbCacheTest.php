<?php

declare(strict_types=1);

namespace LPhenom\Cache\Tests;

use LPhenom\Cache\Driver\DbCache;
use LPhenom\Cache\Exception\CacheException;
use LPhenom\Db\Contract\ConnectionInterface;
use LPhenom\Db\Contract\ResultInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class DbCacheTest extends TestCase
{
    /** @var ConnectionInterface&MockObject */
    private ConnectionInterface $connection;
    private DbCache $cache;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(ConnectionInterface::class);
        $this->cache      = new DbCache($this->connection);
    }

    // ─── helpers ──────────────────────────────────────────────────────────────

    private function makeResult(?array $row): ResultInterface
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('fetchOne')->willReturn($row);
        $result->method('fetchAll')->willReturn($row === null ? [] : [$row]);
        return $result;
    }

    // ─── get ──────────────────────────────────────────────────────────────────

    public function testGetReturnsNullWhenNoRow(): void
    {
        $this->connection->method('query')->willReturn($this->makeResult(null));
        self::assertNull($this->cache->get('missing'));
    }

    public function testGetReturnsValueForValidRow(): void
    {
        $this->connection->method('query')
            ->willReturn($this->makeResult(['cache_value' => 'hello', 'expires_at' => 0]));

        self::assertSame('hello', $this->cache->get('key'));
    }

    public function testGetReturnsNullForExpiredRow(): void
    {
        $past = time() - 10;
        $this->connection->method('query')
            ->willReturn($this->makeResult(['cache_value' => 'stale', 'expires_at' => $past]));

        // Also expect a delete call (lazy expiry)
        $this->connection->expects(self::once())->method('execute');

        self::assertNull($this->cache->get('key'));
    }

    public function testGetReturnsValueWhenTtlStillValid(): void
    {
        $future = time() + 3600;
        $this->connection->method('query')
            ->willReturn($this->makeResult(['cache_value' => 'fresh', 'expires_at' => $future]));

        self::assertSame('fresh', $this->cache->get('key'));
    }

    public function testGetThrowsCacheExceptionWhenQueryFails(): void
    {
        $this->connection->method('query')
            ->willThrowException(new \RuntimeException('connection lost'));

        $this->expectException(CacheException::class);
        $this->cache->get('key');
    }

    // ─── set ──────────────────────────────────────────────────────────────────

    public function testSetExecutesReplaceQuery(): void
    {
        $this->connection->expects(self::once())
            ->method('execute')
            ->with(self::stringContains('REPLACE INTO cache'));

        $this->cache->set('k', 'v');
    }

    public function testSetThrowsCacheExceptionWhenExecuteFails(): void
    {
        $this->connection->method('execute')
            ->willThrowException(new \RuntimeException('write error'));

        $this->expectException(CacheException::class);
        $this->cache->set('k', 'v');
    }

    // ─── delete ───────────────────────────────────────────────────────────────

    public function testDeleteExecutesDeleteQuery(): void
    {
        $this->connection->expects(self::once())
            ->method('execute')
            ->with(self::stringContains('DELETE FROM cache'));

        $this->cache->delete('key');
    }

    public function testDeleteThrowsCacheExceptionWhenExecuteFails(): void
    {
        $this->connection->method('execute')
            ->willThrowException(new \RuntimeException('delete error'));

        $this->expectException(CacheException::class);
        $this->cache->delete('key');
    }

    // ─── has ──────────────────────────────────────────────────────────────────

    public function testHasReturnsTrueWhenKeyExists(): void
    {
        $this->connection->method('query')
            ->willReturn($this->makeResult(['cache_value' => '1', 'expires_at' => 0]));

        self::assertTrue($this->cache->has('key'));
    }

    public function testHasReturnsFalseWhenKeyMissing(): void
    {
        $this->connection->method('query')->willReturn($this->makeResult(null));
        self::assertFalse($this->cache->has('key'));
    }

    // ─── increment ────────────────────────────────────────────────────────────

    public function testIncrementUpdatesExistingRow(): void
    {
        // First execute (UPDATE) returns 1 affected row
        // Then get() is called to fetch new value — query returns updated row
        $this->connection->method('execute')->willReturn(1);
        $this->connection->method('query')
            ->willReturn($this->makeResult(['cache_value' => '11', 'expires_at' => 0]));

        $result = $this->cache->increment('cnt', 1);
        self::assertSame(11, $result);
    }

    public function testIncrementInsertsNewRowWhenNotExists(): void
    {
        // First execute (UPDATE) returns 0 affected rows -> insert path
        $executeCount = 0;
        $this->connection->method('execute')->willReturnCallback(
            function () use (&$executeCount): int {
                $executeCount++;
                return 0; // UPDATE affected nothing -> triggers REPLACE
            }
        );

        $result = $this->cache->increment('new', 7, 0);
        self::assertSame(7, $result);
        self::assertSame(2, $executeCount); // UPDATE + REPLACE
    }

    public function testIncrementThrowsWhenUpdateFails(): void
    {
        $this->connection->method('execute')
            ->willThrowException(new \RuntimeException('query error'));

        $this->expectException(CacheException::class);
        $this->cache->increment('cnt', 1);
    }

    public function testIncrementThrowsWhenInsertFails(): void
    {
        $callCount = 0;
        $this->connection->method('execute')->willReturnCallback(
            function () use (&$callCount): int {
                $callCount++;
                if ($callCount === 1) {
                    return 0; // UPDATE — nothing updated
                }
                throw new \RuntimeException('insert error'); // REPLACE fails
            }
        );

        $this->expectException(CacheException::class);
        $this->cache->increment('cnt', 1);
    }
}

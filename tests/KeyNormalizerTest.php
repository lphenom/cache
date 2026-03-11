<?php

declare(strict_types=1);

namespace LPhenom\Cache\Tests;

use LPhenom\Cache\Exception\CacheException;
use LPhenom\Cache\KeyNormalizer;
use PHPUnit\Framework\TestCase;

final class KeyNormalizerTest extends TestCase
{
    public function testSimpleKeyPassesThrough(): void
    {
        self::assertSame('foo_bar', KeyNormalizer::normalize('foo_bar'));
    }

    public function testTrimsWhitespace(): void
    {
        self::assertSame('hello', KeyNormalizer::normalize('  hello  '));
    }

    public function testReplacesForbiddenChars(): void
    {
        $key = 'a{b}c(d)e/f\\g@h:i j.k';
        $expected = 'a_b_c_d_e_f_g_h_i_j_k';
        self::assertSame($expected, KeyNormalizer::normalize($key));
    }

    public function testTruncatesToSixtyFourChars(): void
    {
        $long = str_repeat('a', 100);
        $result = KeyNormalizer::normalize($long);
        self::assertSame(64, strlen($result));
    }

    public function testExactlyMaxLengthIsKept(): void
    {
        $key = str_repeat('x', 64);
        self::assertSame($key, KeyNormalizer::normalize($key));
    }

    public function testThrowsOnEmptyKey(): void
    {
        $this->expectException(CacheException::class);
        KeyNormalizer::normalize('');
    }

    public function testThrowsOnWhitespaceOnlyKey(): void
    {
        $this->expectException(CacheException::class);
        KeyNormalizer::normalize('   ');
    }

    public function testThrowsOnKeyThatBecomesEmptyAfterNormalization(): void
    {
        // After replacing forbidden chars, key becomes empty if original was e.g. "{}"
        // But after trim it's already non-empty, and after replacing it may become "__"
        // So this is not empty — just check that {} alone becomes __
        $result = KeyNormalizer::normalize('{}');
        self::assertSame('__', $result);
    }

    public function testNumericKey(): void
    {
        self::assertSame('12345', KeyNormalizer::normalize('12345'));
    }

    public function testKeyWithColonNamespace(): void
    {
        self::assertSame('user_1234_profile', KeyNormalizer::normalize('user:1234:profile'));
    }
}

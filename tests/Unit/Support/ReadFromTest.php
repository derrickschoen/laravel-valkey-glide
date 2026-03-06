<?php

declare(strict_types = 1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SineMacula\Valkey\Support\ReadFrom;

/**
 * ReadFrom enum test case.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(ReadFrom::class)]
final class ReadFromTest extends TestCase
{
    /**
     * Provide string strategy names mapped to expected enum cases.
     *
     * @return iterable<string, array{0: string, 1: ReadFrom}>
     */
    public static function stringNameProvider(): iterable
    {
        yield 'primary' => ['primary', ReadFrom::Primary];
        yield 'prefer_replica' => ['prefer_replica', ReadFrom::PreferReplica];
        yield 'az_affinity' => ['az_affinity', ReadFrom::AzAffinity];
        yield 'az_affinity_replicas_and_primary' => ['az_affinity_replicas_and_primary', ReadFrom::AzAffinityReplicasAndPrimary];
    }

    /**
     * Verify string strategy names resolve to the correct enum case.
     *
     * @param  string  $name
     * @param  ReadFrom  $expected
     * @return void
     */
    #[DataProvider('stringNameProvider')]
    #[Test]
    public function tryFromMixedResolvesStringNames(string $name, ReadFrom $expected): void
    {
        self::assertSame($expected, ReadFrom::tryFromMixed($name));
    }

    /**
     * Verify string name resolution is case-insensitive and trims whitespace.
     *
     * @return void
     */
    #[Test]
    public function tryFromMixedIsCaseInsensitiveAndTrimmed(): void
    {
        self::assertSame(ReadFrom::PreferReplica, ReadFrom::tryFromMixed('PREFER_REPLICA'));
        self::assertSame(ReadFrom::PreferReplica, ReadFrom::tryFromMixed(' prefer_replica '));
    }

    /**
     * Provide integer values mapped to expected enum cases.
     *
     * @return iterable<string, array{0: int, 1: ReadFrom}>
     */
    public static function integerValueProvider(): iterable
    {
        yield 'primary (0)' => [0, ReadFrom::Primary];
        yield 'prefer_replica (1)' => [1, ReadFrom::PreferReplica];
        yield 'az_affinity (2)' => [2, ReadFrom::AzAffinity];
        yield 'az_affinity_replicas_and_primary (3)' => [3, ReadFrom::AzAffinityReplicasAndPrimary];
    }

    /**
     * Verify integer values resolve to the correct enum case.
     *
     * @param  int  $value
     * @param  ReadFrom  $expected
     * @return void
     */
    #[DataProvider('integerValueProvider')]
    #[Test]
    public function tryFromMixedResolvesIntegerValues(int $value, ReadFrom $expected): void
    {
        self::assertSame($expected, ReadFrom::tryFromMixed($value));
    }

    /**
     * Provide numeric string values mapped to expected enum cases.
     *
     * @return iterable<string, array{0: string, 1: ReadFrom}>
     */
    public static function numericStringProvider(): iterable
    {
        yield 'string 0' => ['0', ReadFrom::Primary];
        yield 'string 1' => ['1', ReadFrom::PreferReplica];
        yield 'string 2' => ['2', ReadFrom::AzAffinity];
        yield 'string 3' => ['3', ReadFrom::AzAffinityReplicasAndPrimary];
    }

    /**
     * Verify numeric string values resolve to the correct enum case.
     *
     * @param  string  $value
     * @param  ReadFrom  $expected
     * @return void
     */
    #[DataProvider('numericStringProvider')]
    #[Test]
    public function tryFromMixedResolvesNumericStrings(string $value, ReadFrom $expected): void
    {
        self::assertSame($expected, ReadFrom::tryFromMixed($value));
    }

    /**
     * Provide invalid values that must return null.
     *
     * @return iterable<string, array{0: mixed}>
     */
    public static function invalidValueProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'bad string' => ['bad'];
        yield 'out-of-range string' => ['5'];
        yield 'negative int' => [-1];
        yield 'out-of-range int' => [4];
        yield 'boolean true' => [true];
        yield 'boolean false' => [false];
        yield 'float' => [1.5];
        yield 'float string' => ['1.0'];
        yield 'scientific notation string' => ['1e1'];
        yield 'null string' => ['null'];
        yield 'false string' => ['false'];
    }

    /**
     * Verify invalid values return null.
     *
     * @param  mixed  $value
     * @return void
     */
    #[DataProvider('invalidValueProvider')]
    #[Test]
    public function tryFromMixedReturnsNullForInvalidValues(mixed $value): void
    {
        self::assertNull(ReadFrom::tryFromMixed($value));
    }

    /**
     * Verify enum case values match the ValkeyGlide extension constants.
     *
     * @return void
     */
    #[Test]
    public function caseValuesMatchExtensionConstants(): void
    {
        self::assertSame(\ValkeyGlide::READ_FROM_PRIMARY, ReadFrom::Primary->value);
        self::assertSame(\ValkeyGlide::READ_FROM_PREFER_REPLICA, ReadFrom::PreferReplica->value);
        self::assertSame(\ValkeyGlide::READ_FROM_AZ_AFFINITY, ReadFrom::AzAffinity->value);
        self::assertSame(\ValkeyGlide::READ_FROM_AZ_AFFINITY_REPLICAS_AND_PRIMARY, ReadFrom::AzAffinityReplicasAndPrimary->value);
    }
}

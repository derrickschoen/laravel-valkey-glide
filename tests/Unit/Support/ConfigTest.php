<?php

declare(strict_types = 1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SineMacula\Valkey\Support\Config;

/**
 * Config test case.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(Config::class)]
final class ConfigTest extends TestCase
{
    /** @var string Loopback host used in expected normalized addresses. */
    private const string LOOPBACK_HOST = '127.0.0.1';

    /** @var int Default Redis port used in expected normalized addresses. */
    private const int DEFAULT_PORT = 6379;

    /**
     * Verify nested options override top-level options and base config values.
     *
     * @return void
     */
    #[Test]
    public function mergePrioritizesNestedOptionsOverOptionsAndBaseConfig(): void
    {
        $config = [
            'host'     => 'base-host',
            'port'     => 6380,
            'database' => 0,
            'options'  => [
                'host'     => 'nested-host',
                'database' => 3,
            ],
        ];

        $options = [
            'host'     => 'option-host',
            'port'     => 6381,
            'database' => 2,
        ];

        $merged = Config::merge($config, $options);

        self::assertSame('nested-host', $merged['host']);
        self::assertSame(6381, $merged['port']);
        self::assertSame(3, $merged['database']);
        self::assertArrayNotHasKey('options', $merged);
    }

    /**
     * Verify merge ignores non-array nested options payloads.
     *
     * @return void
     */
    #[Test]
    public function mergeIgnoresNonArrayNestedOptions(): void
    {
        $merged = Config::merge([
            'host'    => 'base-host',
            'options' => 'invalid-options',
        ], [
            'host' => 'option-host',
        ]);

        self::assertSame('option-host', $merged['host']);
        self::assertArrayNotHasKey('options', $merged);
    }

    /**
     * Verify single-endpoint connect arguments use package defaults.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsDefaultsToLoopbackAndDefaultPort(): void
    {
        $arguments = Config::connectArguments([]);

        self::assertSame(
            [
                'addresses' => [
                    ['host' => self::LOOPBACK_HOST, 'port' => self::DEFAULT_PORT],
                ],
                'request_timeout' => 3000,
            ],
            $arguments,
        );
    }

    /**
     * Verify configured addresses are normalized and invalid entries ignored.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsNormalizesConfiguredAddressesAndSkipsInvalidEntries(): void
    {
        $arguments = Config::connectArguments([
            'addresses' => [
                ['host' => 'cache-a', 'port' => '6380'],
                'invalid-address',
                ['host' => '', 'port' => 0],
            ],
        ]);

        self::assertSame(
            [
                'addresses' => [
                    ['host' => 'cache-a', 'port' => 6380],
                    ['host' => self::LOOPBACK_HOST, 'port' => self::DEFAULT_PORT],
                ],
                'request_timeout' => 3000,
            ],
            $arguments,
        );
    }

    /**
     * Verify scalar host values are string-normalized for connect arguments.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsNormalizesScalarHostValues(): void
    {
        $arguments = Config::connectArguments([
            'host' => 1234,
            'port' => 6380,
        ]);

        self::assertSame(
            [
                'addresses' => [
                    ['host' => '1234', 'port' => 6380],
                ],
                'request_timeout' => 3000,
            ],
            $arguments,
        );
    }

    /**
     * Provide connection config shapes that must enable TLS.
     *
     * @return iterable<string, array{0: array<string, mixed>}>
     */
    public static function tlsConfigurationProvider(): iterable
    {
        yield 'tls boolean flag' => [['tls' => true]];

        yield 'tls scheme' => [['scheme' => 'tls']];

        yield 'iam configuration' => [
            [
                'iam' => [
                    'username'     => 'iam-user',
                    'cluster_name' => 'cluster-a',
                    'region'       => 'eu-west-1',
                ],
            ],
        ];
    }

    /**
     * Verify TLS is enabled for supported TLS and IAM input shapes.
     *
     * @param  array<string, mixed>  $config
     * @return void
     */
    #[DataProvider('tlsConfigurationProvider')]
    #[Test]
    public function connectArgumentsEnablesTlsWhenConfigured(array $config): void
    {
        $arguments = Config::connectArguments($config);

        self::assertArrayHasKey('use_tls', $arguments);
        self::assertTrue($arguments['use_tls']);
    }

    /**
     * Verify ACL credentials are emitted when username and password are set.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsBuildsPasswordCredentialsIncludingUsernameWhenProvided(): void
    {
        $arguments = Config::connectArguments([
            'username' => 'cache-user',
            'password' => 'secret',
        ]);

        self::assertSame(
            [
                'password' => 'secret',
                'username' => 'cache-user',
            ],
            $arguments['credentials'],
        );
    }

    /**
     * Verify IAM credentials are mapped into GLIDE's expected shape.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsBuildsIamCredentialsInExpectedShape(): void
    {
        $arguments = Config::connectArguments([
            'iam' => [
                'username'         => 'iam-user',
                'cluster_name'     => 'cluster-prod',
                'region'           => 'eu-west-2',
                'refresh_interval' => 120,
            ],
        ]);

        self::assertTrue($arguments['use_tls']);
        self::assertSame(
            [
                'username'  => 'iam-user',
                'iamConfig' => [
                    'clusterName'            => 'cluster-prod',
                    'region'                 => 'eu-west-2',
                    'service'                => 'Elasticache',
                    'refreshIntervalSeconds' => 120,
                ],
            ],
            $arguments['credentials'],
        );
    }

    /**
     * Verify incomplete IAM definitions are ignored for credentials.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsDropsIncompleteIamCredentials(): void
    {
        $arguments = Config::connectArguments([
            'iam' => [
                'username'     => 'iam-user',
                'cluster_name' => '',
                'region'       => 'us-east-1',
            ],
        ]);

        self::assertArrayNotHasKey('credentials', $arguments);
        self::assertTrue($arguments['use_tls']);
    }

    /**
     * Verify database and client name values are normalized when valid.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsIncludesDatabaseAndClientNameWhenValid(): void
    {
        $arguments = Config::connectArguments([
            'database' => '4',
            'name'     => '  worker-a  ',
        ]);

        self::assertSame(4, $arguments['database_id']);
        self::assertSame('worker-a', $arguments['client_name']);
    }

    /**
     * Verify invalid database and client name values are omitted.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsExcludesInvalidDatabaseAndClientNameValues(): void
    {
        $arguments = Config::connectArguments([
            'database' => -1,
            'name'     => '   ',
        ]);

        self::assertArrayNotHasKey('database_id', $arguments);
        self::assertArrayNotHasKey('client_name', $arguments);
    }

    /**
     * Verify cluster node extraction supports flat and nested definitions.
     *
     * @return void
     */
    #[Test]
    public function clusterAddressesReturnsConfiguredNodesFromFlatAndNestedShapes(): void
    {
        $addresses = Config::clusterAddresses([
            ['host' => 'node-1', 'port' => 6380],
            [
                'writer' => ['host' => 'node-2', 'port' => '6381'],
                'reader' => ['host' => 'node-3'],
            ],
            'invalid',
        ]);

        self::assertSame(
            [
                ['host' => 'node-1', 'port' => 6380],
                ['host' => 'node-2', 'port' => 6381],
                ['host' => 'node-3', 'port' => self::DEFAULT_PORT],
            ],
            $addresses,
        );
    }

    /**
     * Verify cluster fallback address is used when no nodes are configured.
     *
     * @return void
     */
    #[Test]
    public function clusterAddressesFallsBackToDefaultAddressWhenNoValidNodesExist(): void
    {
        $addresses = Config::clusterAddresses(['invalid']);

        self::assertSame(
            [
                ['host' => self::LOOPBACK_HOST, 'port' => self::DEFAULT_PORT],
            ],
            $addresses,
        );
    }

    /**
     * Verify cluster connect arguments include seed addresses and base config.
     *
     * @return void
     */
    #[Test]
    public function clusterConnectArgumentsUsesSeedAddressesAndBaseConfiguration(): void
    {
        $arguments = Config::clusterConnectArguments(
            [
                ['host' => 'node-a', 'port' => 6380],
                ['host' => 'node-b', 'port' => 6381],
            ],
            [
                'password' => 'secret',
                'database' => 2,
            ],
        );

        self::assertSame(
            [
                ['host' => 'node-a', 'port' => 6380],
                ['host' => 'node-b', 'port' => 6381],
            ],
            $arguments['addresses'],
        );
        self::assertSame(['password' => 'secret'], $arguments['credentials']);
        self::assertSame(2, $arguments['database_id']);
    }

    /**
     * Verify database ID zero is excluded by default to prevent SELECT 0.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsExcludesDatabaseIdZeroByDefault(): void
    {
        $arguments = Config::connectArguments(['database' => 0]);

        self::assertArrayNotHasKey('database_id', $arguments);
    }

    /**
     * Verify database ID string zero is excluded by default.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsExcludesDatabaseIdStringZeroByDefault(): void
    {
        $arguments = Config::connectArguments(['database' => '0']);

        self::assertArrayNotHasKey('database_id', $arguments);
    }

    /**
     * Verify database ID zero is included when skip_database_zero is disabled.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsIncludesDatabaseIdZeroWhenSkipDisabled(): void
    {
        $arguments = Config::connectArguments([
            'database'           => 0,
            'skip_database_zero' => false,
        ]);

        self::assertSame(0, $arguments['database_id']);
    }

    /**
     * Verify non-zero database IDs are included regardless of skip setting.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsStillIncludesNonZeroDatabaseIds(): void
    {
        $arguments = Config::connectArguments(['database' => 3]);

        self::assertSame(3, $arguments['database_id']);
    }

    /**
     * Provide valid timeout values in seconds with expected millisecond results.
     *
     * @return iterable<string, array{0: mixed, 1: int}>
     */
    public static function validTimeoutProvider(): iterable
    {
        yield 'float seconds' => [2.0, 2000];
        yield 'string seconds' => ['2.5', 2500];
        yield 'integer seconds' => [5, 5000];
        yield 'sub-second' => [0.5, 500];
    }

    /**
     * Verify timeout values in seconds are converted to milliseconds.
     *
     * @param  mixed  $input
     * @param  int  $expected
     * @return void
     */
    #[DataProvider('validTimeoutProvider')]
    #[Test]
    public function connectArgumentsConvertsTimeoutFromSecondsToMilliseconds(mixed $input, int $expected): void
    {
        $arguments = Config::connectArguments(['timeout' => $input]);

        self::assertSame($expected, $arguments['request_timeout']);
    }

    /**
     * Provide invalid timeout values that should be excluded.
     *
     * @return iterable<string, array{0: mixed}>
     */
    public static function invalidTimeoutProvider(): iterable
    {
        yield 'zero' => [0];
        yield 'negative' => [-1];
        yield 'null' => [null];
        yield 'string' => ['abc'];
        yield 'boolean true' => [true];
        yield 'sub-millisecond' => [0.0004];
    }

    /**
     * Verify invalid timeout values fall back to the hardcoded default.
     *
     * @param  mixed  $value
     * @return void
     */
    #[DataProvider('invalidTimeoutProvider')]
    #[Test]
    public function connectArgumentsFallsBackToDefaultTimeoutForInvalidValues(mixed $value): void
    {
        $arguments = Config::connectArguments(['timeout' => $value]);

        self::assertSame(3000, $arguments['request_timeout']);
    }

    /**
     * Verify hardcoded default timeout is applied when no timeout is configured.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsAppliesDefaultTimeoutWhenNoneConfigured(): void
    {
        $arguments = Config::connectArguments([]);

        self::assertSame(3000, $arguments['request_timeout']);
    }

    /**
     * Verify array context values are included for TLS configuration.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsIncludesArrayContext(): void
    {
        $ssl_options = ['ssl' => ['cafile' => '/path/to/ca.crt', 'verify_peer' => true]];

        $arguments = Config::connectArguments(['context' => $ssl_options]);

        self::assertSame($ssl_options, $arguments['context']);
    }

    /**
     * Verify resource context values are included for TLS configuration.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsIncludesResourceContext(): void
    {
        $context = stream_context_create(['ssl' => ['cafile' => '/path/to/ca.crt']]);

        $arguments = Config::connectArguments(['context' => $context]);

        self::assertSame($context, $arguments['context']);
    }

    /**
     * Provide non-context values that should be excluded.
     *
     * @return iterable<string, array{0: mixed}>
     */
    public static function nonContextProvider(): iterable
    {
        yield 'null' => [null];
        yield 'string' => ['not-a-context'];
        yield 'integer' => [123];
        yield 'empty array' => [[]];
    }

    /**
     * Verify non-context values are excluded from connect arguments.
     *
     * @param  mixed  $value
     * @return void
     */
    #[DataProvider('nonContextProvider')]
    #[Test]
    public function connectArgumentsExcludesNonContextValues(mixed $value): void
    {
        $arguments = Config::connectArguments(['context' => $value]);

        self::assertArrayNotHasKey('context', $arguments);
    }
}

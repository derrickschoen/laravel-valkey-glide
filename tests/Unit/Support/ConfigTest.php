<?php

declare(strict_types = 1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SineMacula\Valkey\Support\Config;
use SineMacula\Valkey\Support\ReadFrom;

/**
 * Config test case.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 *
 * @internal
 */
#[CoversClass(Config::class)]
#[CoversClass(ReadFrom::class)]
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
     * Verify database ID zero is included in connect arguments.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsIncludesDatabaseIdZero(): void
    {
        $arguments = Config::connectArguments(['database' => 0]);

        self::assertSame(0, $arguments['database_id']);
    }

    /**
     * Verify non-zero database IDs are included in connect arguments.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsIncludesNonZeroDatabaseIds(): void
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
        yield 'boundary just below 1000' => [999, 999000];
        yield 'boundary at 1000 (milliseconds)' => [1000, 1000];
        yield 'milliseconds passthrough' => [2000, 2000];
        yield 'string milliseconds' => ['3000', 3000];
        yield '120 seconds' => [120, 120000];
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
     * Verify invalid timeout values are excluded from connect arguments.
     *
     * @param  mixed  $value
     * @return void
     */
    #[DataProvider('invalidTimeoutProvider')]
    #[Test]
    public function connectArgumentsExcludesInvalidTimeoutValues(mixed $value): void
    {
        $arguments = Config::connectArguments(['timeout' => $value]);

        self::assertArrayNotHasKey('request_timeout', $arguments);
    }

    /**
     * Verify request timeout is excluded when no timeout is configured.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsExcludesRequestTimeoutWhenNoneConfigured(): void
    {
        $arguments = Config::connectArguments([]);

        self::assertArrayNotHasKey('request_timeout', $arguments);
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

    /**
     * Verify connection timeout is included in advanced config.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsIncludesConnectionTimeoutInAdvancedConfig(): void
    {
        $arguments = Config::connectArguments(['connection_timeout' => 2000]);

        self::assertSame(['connection_timeout' => 2000], $arguments['advanced_config']);
    }

    /**
     * Verify connection timeout auto-detects seconds and converts to milliseconds.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsAutoDetectsConnectionTimeoutSeconds(): void
    {
        $arguments = Config::connectArguments(['connection_timeout' => 2.0]);

        self::assertSame(['connection_timeout' => 2000], $arguments['advanced_config']);
    }

    /**
     * Verify advanced config is excluded when no connection timeout is set.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsExcludesAdvancedConfigWhenNoConnectionTimeout(): void
    {
        $arguments = Config::connectArguments([]);

        self::assertArrayNotHasKey('advanced_config', $arguments);
    }

    /**
     * Verify zero connection timeout is excluded.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsExcludesZeroConnectionTimeout(): void
    {
        $arguments = Config::connectArguments(['connection_timeout' => 0]);

        self::assertArrayNotHasKey('advanced_config', $arguments);
    }

    /**
     * Verify negative connection timeout is excluded.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsExcludesNegativeConnectionTimeout(): void
    {
        $arguments = Config::connectArguments(['connection_timeout' => -1]);

        self::assertArrayNotHasKey('advanced_config', $arguments);
    }

    /**
     * Verify non-numeric connection timeout is excluded.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsExcludesNonNumericConnectionTimeout(): void
    {
        $arguments = Config::connectArguments(['connection_timeout' => 'abc']);

        self::assertArrayNotHasKey('advanced_config', $arguments);
    }

    /**
     * Verify read_from is resolved and included when configured.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsIncludesReadFromWhenConfigured(): void
    {
        $arguments = Config::connectArguments(['read_from' => 'prefer_replica']);

        self::assertSame(1, $arguments['read_from']);
    }

    /**
     * Verify read_from zero (Primary) is preserved and not dropped by falsy check.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsIncludesReadFromZero(): void
    {
        $arguments = Config::connectArguments(['read_from' => 0]);

        self::assertSame(0, $arguments['read_from']);
    }

    /**
     * Verify read_from is excluded when not configured.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsExcludesReadFromWhenNotConfigured(): void
    {
        $arguments = Config::connectArguments([]);

        self::assertArrayNotHasKey('read_from', $arguments);
    }

    /**
     * Verify client_az is included when a valid string is configured.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsIncludesClientAzWhenValid(): void
    {
        $arguments = Config::connectArguments(['client_az' => '  use1-az1  ']);

        self::assertSame('use1-az1', $arguments['client_az']);
    }

    /**
     * Provide invalid client_az values that should be excluded.
     *
     * @return iterable<string, array{0: mixed}>
     */
    public static function invalidClientAzProvider(): iterable
    {
        yield 'null' => [null];
        yield 'empty string' => [''];
        yield 'whitespace only' => ['   '];
        yield 'boolean true' => [true];
        yield 'boolean false' => [false];
        yield 'integer' => [123];
        yield 'float' => [1.5];
    }

    /**
     * Verify invalid client_az values are excluded from connect arguments.
     *
     * @param  mixed  $value
     * @return void
     */
    #[DataProvider('invalidClientAzProvider')]
    #[Test]
    public function connectArgumentsExcludesInvalidClientAzValues(mixed $value): void
    {
        $arguments = Config::connectArguments(['client_az' => $value]);

        self::assertArrayNotHasKey('client_az', $arguments);
    }

    // ─── ElastiCache Serverless ──────────────────────────────────────────

    /**
     * Verify elasticache_serverless forces database_id to null even when explicitly set.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsWithElastiCacheServerlessForcesNullDatabaseId(): void
    {
        $arguments = Config::connectArguments([
            'elasticache_serverless' => true,
            'database'              => 5,
        ]);

        self::assertArrayNotHasKey('database_id', $arguments);
    }

    /**
     * Verify elasticache_serverless forces TLS on.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsWithElastiCacheServerlessForcesTls(): void
    {
        $arguments = Config::connectArguments([
            'elasticache_serverless' => true,
        ]);

        self::assertTrue($arguments['use_tls']);
    }

    /**
     * Verify elasticache_serverless auto-generates two addresses from host.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsWithElastiCacheServerlessAutoGeneratesAddresses(): void
    {
        $arguments = Config::connectArguments([
            'elasticache_serverless' => true,
            'host'                  => 'my-cache.serverless.use1.cache.amazonaws.com',
            'port'                  => 6379,
        ]);

        self::assertSame(
            [
                ['host' => 'my-cache.serverless.use1.cache.amazonaws.com', 'port' => 6379],
                ['host' => 'my-cache.serverless.use1.cache.amazonaws.com', 'port' => 6380],
            ],
            $arguments['addresses'],
        );
    }

    /**
     * Verify elasticache_serverless preserves explicitly configured addresses.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsWithElastiCacheServerlessPreservesExplicitAddresses(): void
    {
        $explicit_addresses = [
            ['host' => 'custom-a', 'port' => 7000],
            ['host' => 'custom-b', 'port' => 7001],
        ];

        $arguments = Config::connectArguments([
            'elasticache_serverless' => true,
            'addresses'             => $explicit_addresses,
        ]);

        self::assertSame(
            [
                ['host' => 'custom-a', 'port' => 7000],
                ['host' => 'custom-b', 'port' => 7001],
            ],
            $arguments['addresses'],
        );
    }

    /**
     * Verify elasticache_serverless defaults timeouts to 3000ms when not set.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsWithElastiCacheServerlessDefaultsTimeouts(): void
    {
        $arguments = Config::connectArguments([
            'elasticache_serverless' => true,
        ]);

        self::assertSame(3000, $arguments['request_timeout']);
        self::assertSame(['connection_timeout' => 3000], $arguments['advanced_config']);
    }

    /**
     * Verify elasticache_serverless enforces minimum 2000ms for timeouts.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsWithElastiCacheServerlessEnforcesMinTimeout(): void
    {
        $arguments = Config::connectArguments([
            'elasticache_serverless' => true,
            'timeout'               => 1500,
            'connection_timeout'    => 1800,
        ]);

        self::assertSame(2000, $arguments['request_timeout']);
        self::assertSame(['connection_timeout' => 2000], $arguments['advanced_config']);
    }

    /**
     * Verify elasticache_serverless defaults read_from to PreferReplica.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsWithElastiCacheServerlessDefaultsReadFrom(): void
    {
        $arguments = Config::connectArguments([
            'elasticache_serverless' => true,
        ]);

        self::assertSame(ReadFrom::PreferReplica->value, $arguments['read_from']);
    }

    /**
     * Verify elasticache_serverless preserves explicit read_from value.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsWithElastiCacheServerlessPreservesExplicitReadFrom(): void
    {
        $arguments = Config::connectArguments([
            'elasticache_serverless' => true,
            'read_from'             => 'primary',
        ]);

        self::assertSame(ReadFrom::Primary->value, $arguments['read_from']);
    }

    /**
     * Verify elasticache_serverless falls back to PreferReplica when read_from is invalid.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsWithElastiCacheServerlessFallsBackToPreferReplicaOnInvalidReadFrom(): void
    {
        $arguments = Config::connectArguments([
            'elasticache_serverless' => true,
            'read_from'             => 'garbage',
        ]);

        self::assertSame(ReadFrom::PreferReplica->value, $arguments['read_from']);
    }

    /**
     * Verify flag false or missing has no effect on behavior.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsWithoutElastiCacheServerlessUnchanged(): void
    {
        $arguments_without = Config::connectArguments([
            'host'     => 'my-cache',
            'database' => 3,
        ]);

        $arguments_false = Config::connectArguments([
            'host'                  => 'my-cache',
            'database'              => 3,
            'elasticache_serverless' => false,
        ]);

        self::assertSame($arguments_without, $arguments_false);
        self::assertSame(3, $arguments_without['database_id']);
        self::assertArrayNotHasKey('use_tls', $arguments_without);
        self::assertArrayNotHasKey('request_timeout', $arguments_without);
    }

    // ─── ElastiCache Serverless Auto-Detection ─────────────────────────────

    /**
     * Verify serverless mode is auto-detected from host matching the ElastiCache Serverless pattern.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsAutoDetectsElastiCacheServerlessFromHost(): void
    {
        $arguments = Config::connectArguments([
            'host'     => 'my-cache.serverless.use1.cache.amazonaws.com',
            'database' => 5,
        ]);

        self::assertArrayNotHasKey('database_id', $arguments);
        self::assertTrue($arguments['use_tls']);
        self::assertSame(ReadFrom::PreferReplica->value, $arguments['read_from']);
        self::assertSame(3000, $arguments['request_timeout']);
    }

    /**
     * Verify serverless mode is auto-detected from url matching the ElastiCache Serverless pattern.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsAutoDetectsElastiCacheServerlessFromUrl(): void
    {
        $arguments = Config::connectArguments([
            'url'      => 'tls://my-cache.serverless.use1.cache.amazonaws.com:6379',
            'database' => 3,
        ]);

        self::assertArrayNotHasKey('database_id', $arguments);
        self::assertTrue($arguments['use_tls']);
        self::assertSame(ReadFrom::PreferReplica->value, $arguments['read_from']);
    }

    /**
     * Verify non-serverless ElastiCache hostnames do not trigger auto-detection.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsDoesNotAutoDetectNonServerlessElastiCacheHost(): void
    {
        $arguments = Config::connectArguments([
            'host'     => 'my-cache.use1.cache.amazonaws.com',
            'database' => 3,
        ]);

        self::assertSame(3, $arguments['database_id']);
        self::assertArrayNotHasKey('use_tls', $arguments);
        self::assertArrayNotHasKey('read_from', $arguments);
    }

    /**
     * Verify plain hostnames do not trigger auto-detection.
     *
     * @return void
     */
    #[Test]
    public function connectArgumentsDoesNotAutoDetectPlainHostname(): void
    {
        $arguments = Config::connectArguments([
            'host'     => 'redis.internal',
            'database' => 2,
        ]);

        self::assertSame(2, $arguments['database_id']);
        self::assertArrayNotHasKey('use_tls', $arguments);
        self::assertArrayNotHasKey('read_from', $arguments);
    }

    /**
     * Verify nested options override top-level read_from and client_az values.
     *
     * @return void
     */
    #[Test]
    public function mergePreservesReadFromAndClientAzFromNestedOptions(): void
    {
        $merged = Config::merge(
            [
                'read_from' => 'primary',
                'client_az' => 'old-az',
                'options'   => [
                    'read_from' => 'prefer_replica',
                    'client_az' => 'use1-az1',
                ],
            ],
        );

        self::assertSame('prefer_replica', $merged['read_from']);
        self::assertSame('use1-az1', $merged['client_az']);
    }
}

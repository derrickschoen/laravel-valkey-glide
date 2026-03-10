<?php

declare(strict_types = 1);

namespace SineMacula\Valkey\Support;

/**
 * Backed enum for GLIDE read-from routing strategies.
 *
 * @author      Ben Carey <bdmc@sinemacula.co.uk>
 * @copyright   2026 Sine Macula Limited.
 */
enum ReadFrom: int
{
    case Primary                      = 0;
    case PreferReplica                = 1;
    case AzAffinity                   = 2;
    case AzAffinityReplicasAndPrimary = 3;

    /** @var array<string, self> */
    private const array NAME_MAP = [
        'primary'                          => self::Primary,
        'prefer_replica'                   => self::PreferReplica,
        'az_affinity'                      => self::AzAffinity,
        'az_affinity_replicas_and_primary' => self::AzAffinityReplicasAndPrimary,
    ];

    /**
     * Resolve a read-from strategy from a mixed config value.
     *
     * Accepts integer constants, string strategy names, and numeric
     * strings. Returns null when the value cannot be resolved.
     *
     * @param  mixed  $value
     * @return self|null
     */
    public static function tryFromMixed(mixed $value): ?self
    {
        if (is_int($value)) {
            return self::tryFrom($value);
        }

        if (!is_string($value)) {
            return null;
        }

        $trimmed = strtolower(trim($value));

        if (array_key_exists($trimmed, self::NAME_MAP)) {
            return self::NAME_MAP[$trimmed];
        }

        $int = filter_var($trimmed, FILTER_VALIDATE_INT);

        return $int !== false ? self::tryFrom($int) : null;
    }
}

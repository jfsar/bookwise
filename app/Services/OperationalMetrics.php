<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Log;

/**
 * Emits the §0.30 operational metrics as structured log records so a log
 * aggregator can derive counters and latencies without a dedicated metrics
 * backend. Every record uses the shared `operational_metric` event name and
 * carries `{metric, value, unit, tags}`.
 */
class OperationalMetrics
{
    public const EVENT = 'operational_metric';

    /**
     * @param  array<string, scalar|null>  $tags
     */
    public function increment(string $metric, array $tags = [], int $value = 1): void
    {
        $this->emit($metric, $value, 'count', $tags);
    }

    /**
     * @param  array<string, scalar|null>  $tags
     */
    public function timing(string $metric, float $milliseconds, array $tags = []): void
    {
        $this->emit($metric, round($milliseconds, 3), 'milliseconds', $tags);
    }

    /**
     * Run a callback and record how long it took, returning its result.
     *
     * @param  Closure(): mixed  $callback
     * @param  array<string, scalar|null>  $tags
     */
    public function measure(string $metric, Closure $callback, array $tags = []): mixed
    {
        $startedAt = hrtime(true);

        try {
            return $callback();
        } finally {
            $this->timing($metric, (hrtime(true) - $startedAt) / 1_000_000, $tags);
        }
    }

    /**
     * @param  array<string, scalar|null>  $tags
     */
    private function emit(string $metric, int|float $value, string $unit, array $tags): void
    {
        Log::info(self::EVENT, [
            'metric' => $metric,
            'value' => $value,
            'unit' => $unit,
            'tags' => $tags,
        ]);
    }
}

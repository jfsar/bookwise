<?php

namespace Tests\Feature;

use Tests\TestCase;

class RedisConfigurationTest extends TestCase
{
    public function test_redis_cache_and_queue_connections_target_database_zero(): void
    {
        $this->assertSame('0', config('database.redis.cache.database'));
        $this->assertSame('0', config('database.redis.default.database'));
    }

    public function test_redis_queue_retry_after_exceeds_the_queue_worker_timeout(): void
    {
        $retryAfter = (int) config('queue.connections.redis.retry_after');

        $timeouts = $this->queueWorkerTimeouts();

        $this->assertNotEmpty($timeouts);

        foreach ($timeouts as $timeout) {
            $this->assertGreaterThan($timeout, $retryAfter);
        }
    }

    /**
     * The --timeout the queue worker runs with, from every place it is declared.
     *
     * @return list<int>
     */
    private function queueWorkerTimeouts(): array
    {
        $timeouts = [];

        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, flags: JSON_THROW_ON_ERROR);

        foreach ($composer['scripts']['queue'] as $line) {
            if (preg_match('/--timeout=(\d+)/', $line, $matches)) {
                $timeouts[] = (int) $matches[1];
            }
        }

        $appYaml = (string) file_get_contents(base_path('app.yaml'));

        if (preg_match_all('/--timeout=(\d+)/', $appYaml, $matches)) {
            foreach ($matches[1] as $timeout) {
                $timeouts[] = (int) $timeout;
            }
        }

        return $timeouts;
    }
}

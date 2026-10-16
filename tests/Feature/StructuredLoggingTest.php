<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Tests\TestCase;

class StructuredLoggingTest extends TestCase
{
    public function test_the_structured_channel_is_configured_with_a_json_formatter(): void
    {
        $this->assertSame('monolog', config('logging.channels.json.driver'));
        $this->assertSame(JsonFormatter::class, config('logging.channels.json.formatter'));
    }

    public function test_log_entries_carry_the_correlation_context(): void
    {
        $handler = new TestHandler;

        Log::channel('json')->getLogger()->pushHandler($handler);

        Context::add('correlation_id', 'corr-99');
        Context::add('request_id', 'req-99');

        Log::channel('json')->info('Diagnostic message');

        $this->assertTrue($handler->hasInfoThatContains('Diagnostic message'));

        $record = $handler->getRecords()[0];

        $this->assertSame('corr-99', $record['extra']['correlation_id']);
        $this->assertSame('req-99', $record['extra']['request_id']);
    }
}

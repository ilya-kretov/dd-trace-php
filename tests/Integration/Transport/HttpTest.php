<?php

namespace DDTrace\Tests\Integration\Transport;

use DDTrace\Encoders\Json;
use DDTrace\Tracer;
use DDTrace\Transport\Http;
use PHPUnit\Framework;
use Prophecy\Argument;
use Psr\Log\LoggerInterface;

final class HttpTest extends Framework\TestCase
{
    public function testSpanReportingFailsOnUnavailableAgent()
    {
        $logger = $this->prophesize(LoggerInterface::class);
        $logger
            ->debug(
                'Reporting of spans failed: Failed to connect to 0.0.0.0 port 8127: Connection refused, error code 7'
            )
            ->shouldBeCalled();

        $httpTransport = new Http(new Json(), $logger->reveal(), [
            'endpoint' => 'http://0.0.0.0:8127/v0.3/traces'
        ]);
        $tracer = new Tracer($httpTransport);

        $span = $tracer->startSpan('test', [
            'tags' => [
                'key1' => 'value1',
            ]
        ]);

        $span->finish();

        $traces = [
            [$span],
        ];

        $httpTransport->send($traces);
    }

    public function testSpanReportingSuccess()
    {
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->debug(Argument::any())->shouldNotBeCalled();

        $httpTransport = new Http(new Json(), $logger->reveal(), [
            'endpoint' => 'http://0.0.0.0:8126/v0.3/traces'
        ]);
        $tracer = new Tracer($httpTransport);

        $span = $tracer->startSpan('test', [
            'tags' => [
                'key1' => 'value1',
            ]
        ]);

        $childSpan = $tracer->startSpan('child_test', [
            'child_of' => $span,
            'tags' => [
                'key2' => 'value2',
            ]
        ]);

        $childSpan->finish();

        $span->finish();

        $traces = [
            [$span, $childSpan],
        ];

        $httpTransport->send($traces);
    }

    public function testSilentlySendTraces()
    {
        $logger = $this->prophesize(LoggerInterface::class);
        $logger->debug(Argument::any())->shouldNotBeCalled();

        $httpTransport = new Http(new Json(), $logger->reveal(), [
            'endpoint' => 'http://0.0.0.0:8126/v0.3/traces'
        ]);
        $tracer = new Tracer($httpTransport);

        $span = $tracer->startSpan('test');
        $span->finish();

        $traces = [[$span]];

        ob_start();
        $httpTransport->send($traces);
        $output = ob_get_clean();

        $this->assertSame('', $output);
    }
}

<?php

declare(strict_types=1);

namespace AdyenPayment\Tests\Unit\Certificate\Middleware;

use AdyenPayment\Certificate\Logging\ResponseStatusToLogLevelProviderInterface;
use AdyenPayment\Certificate\Middleware\BodyLoggingMiddleware;
use AdyenPayment\Certificate\Middleware\MiddlewareInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Psr\Log\LoggerInterface;

class BodyLoggingMiddlewareTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @var ObjectProphecy|ResponseStatusToLogLevelProviderInterface
     */
    private $responseStatusToLogLevel;

    /**
     * @var LoggerInterface|ObjectProphecy
     */
    private $logger;
    private BodyLoggingMiddleware $bodyLoggingMiddleware;

    protected function setUp(): void
    {
        $this->responseStatusToLogLevel = $this->prophesize(ResponseStatusToLogLevelProviderInterface::class);
        $this->logger = $this->prophesize(LoggerInterface::class);

        $this->bodyLoggingMiddleware = new BodyLoggingMiddleware(
            $this->responseStatusToLogLevel->reveal(),
            $this->logger->reveal()
        );
    }

    /** @test */
    public function it_is_a_client_middleware(): void
    {
        static::assertInstanceOf(MiddlewareInterface::class, $this->bodyLoggingMiddleware);
    }

    /** @test */
    public function it_logs_request_and_response_body(): void
    {
        $responseBody = 'This is an example response body';

        $mock = new MockHandler([
            new Response(204, [], $responseBody),
        ]);

        $stack = HandlerStack::create($mock);
        $stack->unshift($this->bodyLoggingMiddleware);

        $client = new Client(['handler' => $stack]);

        $logLevel = 123;
        $this->responseStatusToLogLevel->__invoke($responseBody)->willReturn(
            $logLevel
        );

        $this->logger->debug(
            'Request to Adyen - apple pay certificate',
            [
                'body' => $requestBody = 'This is an example request body',
            ]
        )->shouldBeCalled();

        $this->logger->log(
            $logLevel,
            'Response from Adyen - apple pay certificate',
            [
                'body' => mb_substr($responseBody, 0, 5),
            ]
        )->shouldBeCalled();

        $client->send(
            new Request(
                'POST',
                '/some-uri-for-testing',
                [],
                $requestBody,
            )
        );
    }
}

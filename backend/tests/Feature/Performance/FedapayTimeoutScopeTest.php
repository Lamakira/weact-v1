<?php

declare(strict_types=1);

namespace Tests\Feature\Performance;

use App\Services\FedapayService;
use FedaPay\HttpClient\CurlClient;
use FedaPay\Payout;
use FedaPay\Transaction;
use Tests\TestCase;

/**
 * The short timeouts only wrap the status READS of the polled endpoints.
 * Writes (transaction create, token, payouts, refunds) keep the SDK defaults:
 * a payout executed remotely but answered late must never surface as a timeout.
 */
class FedapayTimeoutScopeTest extends TestCase
{
    private ?CurlClient $originalInstance = null;

    /** @var list<array{method: string, timeout: int, connect: int}> */
    public array $seen = [];

    protected function setUp(): void
    {
        parent::setUp();

        $reflection = new \ReflectionProperty(CurlClient::class, 'instance');
        $this->originalInstance = $reflection->getValue();

        $test = $this;
        $fake = new class($test) extends CurlClient
        {
            public function __construct(private FedapayTimeoutScopeTest $test)
            {
                parent::__construct();
            }

            public function request($method, $absUrl, $params, $headers)
            {
                $this->test->seen[] = [
                    'method' => strtolower($method),
                    'timeout' => $this->getTimeout(),
                    'connect' => $this->getConnectTimeout(),
                ];

                return [
                    json_encode(['v1/transaction' => ['klass' => 'v1/transaction', 'id' => 1, 'status' => 'pending'], 'v1/payout' => ['klass' => 'v1/payout', 'id' => 2, 'status' => 'pending']]),
                    200,
                    [],
                ];
            }
        };

        $reflection->setValue(null, $fake);
        \FedaPay\Requestor::setHttpClient($fake);
    }

    protected function tearDown(): void
    {
        (new \ReflectionProperty(CurlClient::class, 'instance'))->setValue(null, $this->originalInstance);
        \FedaPay\Requestor::setHttpClient(null);

        parent::tearDown();
    }

    public function test_status_read_uses_short_timeouts_and_restores_the_previous_ones(): void
    {
        $service = new FedapayService;
        $client = CurlClient::instance();
        $client->setTimeout(33)->setConnectTimeout(11);

        $service->retrieveTransaction(1);

        $this->assertSame(10, $this->seen[0]['timeout']);
        $this->assertSame(5, $this->seen[0]['connect']);
        $this->assertSame(33, $client->getTimeout());
        $this->assertSame(11, $client->getConnectTimeout());
    }

    public function test_timeouts_are_restored_when_the_read_fails(): void
    {
        $service = new FedapayService;
        $client = CurlClient::instance();
        $client->setTimeout(CurlClient::DEFAULT_TIMEOUT)->setConnectTimeout(CurlClient::DEFAULT_CONNECT_TIMEOUT);

        // Unparseable body => the SDK throws inside the read.
        $throwing = new class extends CurlClient
        {
            public function request($method, $absUrl, $params, $headers)
            {
                throw new \RuntimeException('network down');
            }
        };
        $throwing->setTimeout(CurlClient::DEFAULT_TIMEOUT)->setConnectTimeout(CurlClient::DEFAULT_CONNECT_TIMEOUT);
        (new \ReflectionProperty(CurlClient::class, 'instance'))->setValue(null, $throwing);
        \FedaPay\Requestor::setHttpClient($throwing);

        try {
            $service->retrieveTransaction(1);
            $this->fail('Expected the read to throw');
        } catch (\RuntimeException) {
        }

        $this->assertSame(CurlClient::DEFAULT_TIMEOUT, $throwing->getTimeout());
        $this->assertSame(CurlClient::DEFAULT_CONNECT_TIMEOUT, $throwing->getConnectTimeout());
    }

    public function test_writes_keep_the_sdk_default_timeouts(): void
    {
        new FedapayService;
        CurlClient::instance()->setTimeout(CurlClient::DEFAULT_TIMEOUT)->setConnectTimeout(CurlClient::DEFAULT_CONNECT_TIMEOUT);

        Transaction::create(['amount' => 100, 'currency' => ['iso' => 'XOF']]);
        Payout::create(['amount' => 100, 'currency' => ['iso' => 'XOF'], 'mode' => 'mtn_open']);

        $this->assertNotEmpty($this->seen);
        foreach ($this->seen as $call) {
            $this->assertSame(CurlClient::DEFAULT_TIMEOUT, $call['timeout'], $call['method']);
            $this->assertSame(CurlClient::DEFAULT_CONNECT_TIMEOUT, $call['connect'], $call['method']);
        }
    }

    public function test_constructing_the_service_does_not_change_the_client_timeouts(): void
    {
        $client = CurlClient::instance();
        $client->setTimeout(CurlClient::DEFAULT_TIMEOUT)->setConnectTimeout(CurlClient::DEFAULT_CONNECT_TIMEOUT);

        new FedapayService;

        $this->assertSame(CurlClient::DEFAULT_TIMEOUT, $client->getTimeout());
        $this->assertSame(CurlClient::DEFAULT_CONNECT_TIMEOUT, $client->getConnectTimeout());
    }
}

<?php

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\Psr7;
use GuzzleHttp\Psr7\Response;
use Illuminate\Http\Request;
use Illuminate\Routing\UrlGenerator;
use jdavidbakr\CloudfrontProxies\CloudfrontProxies;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Psr\Http\Message\ResponseInterface;

class CloudfrontProxiesTest extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Trusted proxies are static in Symfony; reset between tests.
        $request = new Request();
        $request->setTrustedProxies([], app('config')->get('cloudfront-proxies.trust-proxies-headers'));

        app('cache')->forget('cloudfront-proxy-ip-addresses');
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cloudfront-proxies.ip-range-data-url', 'https://ip-ranges.amazonaws.com/ip-ranges.json');
        $app['config']->set('cloudfront-proxies.viewer-address-attribute', null);
        $app['config']->set('cloudfront-proxies.trust-proxies-headers', Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB);
    }

    /**
     * @test
     */
    public function it_downloads_cloudfront_ips()
    {
        $request = new Request(
            [], // query
            [], // request
            [], // attributes
            [], // cookies
            [], // files
            [
                'HTTP_CLOUDFRONT_FORWARDED_PROTO' => 'https'
            ], // server
            null // content
        );
        $middleware = new CloudfrontProxies;
        $mock = Mockery::mock(Guzzle::class);
        $response = new Response(200, [
            'Content-Type' => 'application/json'
        ], json_encode([
                    'prefixes' => [
                        [
                            'ip_prefix' => '127.0.0.1/16',
                            'region' => 'GLOBAL',
                            'service' => 'CLOUDFRONT'
                        ]
                    ]
        ]));
        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn($response);
        app()->instance(Guzzle::class, $mock);

        $middleware->handle($request, function () {
        });

        // Verify that trusted proxies got properly set
        $this->assertEquals(['127.0.0.1/16'], $request->getTrustedProxies());
    }

    /**
     * @test
     */
    public function it_retains_existing_trusted_ip_addresses()
    {
        $request = new Request(
            [], // query
            [], // request
            [], // attributes
            [], // cookies
            [], // files
            [
                'HTTP_CLOUDFRONT_FORWARDED_PROTO' => 'https'
            ], // server
            null // content
        );
        $request->setTrustedProxies(['123.45.67/8'], Request::HEADER_X_FORWARDED_FOR |
            Request::HEADER_X_FORWARDED_HOST |
            Request::HEADER_X_FORWARDED_PORT |
            Request::HEADER_X_FORWARDED_PROTO |
            Request::HEADER_X_FORWARDED_AWS_ELB);
        $middleware = new CloudfrontProxies;
        $mock = Mockery::mock(Guzzle::class);
        $response = new Response(200, [
            'Content-Type' => 'application/json'
        ], json_encode([
                    'prefixes' => [
                        [
                            'ip_prefix' => '127.0.0.1/16',
                            'region' => 'GLOBAL',
                            'service' => 'CLOUDFRONT'
                        ]
                    ]
        ]));
        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn($response);
        app()->instance(Guzzle::class, $mock);

        $middleware->handle($request, function () {
        });

        // Verify that trusted proxies got properly set
        $this->assertEquals(['123.45.67/8', '127.0.0.1/16'], $request->getTrustedProxies());
    }

    /**
     * @test
     */
    public function it_rewrites_proto_headers()
    {
        $request = new Request(
            [], // query
            [], // request
            [], // attributes
            [], // cookies
            [], // files
            [
                'HTTP_CLOUDFRONT_FORWARDED_PROTO' => 'https',
            ], // server
            null // content
        );
        $middleware = new CloudfrontProxies;
        $mock = Mockery::mock(Guzzle::class);
        $response = new Response(200, [
            'Content-Type' => 'application/json'
        ], json_encode([
                    'prefixes' => [
                        [
                            'ip_prefix' => '127.0.0.1/16',
                            'region' => 'GLOBAL',
                            'service' => 'CLOUDFRONT'
                        ]
                    ]
                ]));
        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn($response);
        app()->instance(Guzzle::class, $mock);

        $middleware->handle($request, function () {
        });

        // Verify that trusted proxies got properly set
        $this->assertEquals('https', $request->header('x-forwarded-proto'));
    }

    /**
     * @test
     */
    public function it_rewrites_port_headers()
    {
        $request = new Request(
            [], // query
            [], // request
            [], // attributes
            [], // cookies
            [], // files
            [
                'HTTP_CLOUDFRONT_FORWARDED_PORT' => '443',
            ], // server
            null // content
        );
        $middleware = new CloudfrontProxies;
        $mock = Mockery::mock(Guzzle::class);
        $response = new Response(200, [
            'Content-Type' => 'application/json'
        ], json_encode([
                    'prefixes' => [
                        [
                            'ip_prefix' => '127.0.0.1/16',
                            'region' => 'GLOBAL',
                            'service' => 'CLOUDFRONT'
                        ]
                    ]
                ]));
        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn($response);
        app()->instance(Guzzle::class, $mock);

        $middleware->handle($request, function () {
        });

        // Verify that trusted proxies got properly set
        $this->assertEquals('443', $request->header('x-forwarded-port'));
    }

    /**
     * @test
     */
    public function it_rewrites_proto_and_port_headers()
    {
        $request = new Request(
            [], // query
            [], // request
            [], // attributes
            [], // cookies
            [], // files
            [
                'HTTP_CLOUDFRONT_FORWARDED_PORT' => '443',
                'HTTP_CLOUDFRONT_FORWARDED_PROTO' => 'https',
            ], // server
            null // content
        );
        $middleware = new CloudfrontProxies;
        $mock = Mockery::mock(Guzzle::class);
        $response = new Response(200, [
            'Content-Type' => 'application/json'
        ], json_encode([
                    'prefixes' => [
                        [
                            'ip_prefix' => '127.0.0.1/16',
                            'region' => 'GLOBAL',
                            'service' => 'CLOUDFRONT'
                        ]
                    ]
                ]));
        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn($response);
        app()->instance(Guzzle::class, $mock);

        $middleware->handle($request, function () {
        });

        // Verify that trusted proxies got properly set
        $this->assertEquals('https', $request->header('x-forwarded-proto'));
        $this->assertEquals('443', $request->header('x-forwarded-port'));
    }

    /**
     * @test
     */
    public function we_properly_get_https_routes()
    {
        $request = new Request(
            [], // query
            [], // request
            [], // attributes
            [], // cookies
            [], // files
            [
                'HTTP_CLOUDFRONT_FORWARDED_PROTO' => 'https',
                'REMOTE_ADDR' => '127.0.0.1',
                'HTTP_HOST' => 'localhost',
            ], // server
            null // content
        );
        $middleware = new CloudfrontProxies;
        $mock = Mockery::mock(Guzzle::class);
        $response = new Response(200, [
            'Content-Type' => 'application/json'
        ], json_encode([
                    'prefixes' => [
                        [
                            'ip_prefix' => '127.0.0.1/16',
                            'region' => 'GLOBAL',
                            'service' => 'CLOUDFRONT'
                        ]
                    ]
                ]));
        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn($response);
        app()->instance(Guzzle::class, $mock);
        $routes = app('router')->getRoutes();
        $url = new UrlGenerator($routes, $request);

        $middleware->handle($request, function () {
        });

        $this->assertEquals('https://localhost', $url->to('/'));
    }

    /**
     * @test
     */
    public function it_triggers_on_cloudfront_viewer_address_header()
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            [
                'HTTP_CLOUDFRONT_VIEWER_ADDRESS' => '198.51.100.25:443',
            ],
            null
        );

        $middleware = new CloudfrontProxies;
        $mock = Mockery::mock(Guzzle::class);
        $response = new Response(200, [
            'Content-Type' => 'application/json'
        ], json_encode([
            'prefixes' => [
                [
                    'ip_prefix' => '127.0.0.1/16',
                    'region' => 'GLOBAL',
                    'service' => 'CLOUDFRONT'
                ]
            ]
        ]));

        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn($response);
        app()->instance(Guzzle::class, $mock);

        $middleware->handle($request, function () {
        });

        $this->assertEquals(['127.0.0.1/16'], $request->getTrustedProxies());
    }

    /**
     * @test
     */
    public function it_triggers_on_x_amz_cf_id_header()
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            [
                'HTTP_X_AMZ_CF_ID' => 'example-request-id',
            ],
            null
        );

        $middleware = new CloudfrontProxies;
        $mock = Mockery::mock(Guzzle::class);
        $response = new Response(200, [
            'Content-Type' => 'application/json'
        ], json_encode([
            'prefixes' => [
                [
                    'ip_prefix' => '127.0.0.1/16',
                    'region' => 'GLOBAL',
                    'service' => 'CLOUDFRONT'
                ]
            ]
        ]));

        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn($response);
        app()->instance(Guzzle::class, $mock);

        $middleware->handle($request, function () {
        });

        $this->assertEquals(['127.0.0.1/16'], $request->getTrustedProxies());
    }

    /**
     * @test
     */
    public function it_downloads_cloudfront_ipv4_and_ipv6_ips()
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            [
                'HTTP_X_AMZ_CF_ID' => 'example-request-id',
            ],
            null
        );

        $middleware = new CloudfrontProxies;
        $mock = Mockery::mock(Guzzle::class);
        $response = new Response(200, [
            'Content-Type' => 'application/json'
        ], json_encode([
            'prefixes' => [
                [
                    'ip_prefix' => '127.0.0.1/16',
                    'region' => 'GLOBAL',
                    'service' => 'CLOUDFRONT'
                ]
            ],
            'ipv6_prefixes' => [
                [
                    'ipv6_prefix' => '2600:9000::/28',
                    'region' => 'GLOBAL',
                    'service' => 'CLOUDFRONT'
                ]
            ]
        ]));

        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn($response);
        app()->instance(Guzzle::class, $mock);

        $middleware->handle($request, function () {
        });

        $this->assertEquals(['127.0.0.1/16', '2600:9000::/28'], $request->getTrustedProxies());
    }

    /**
     * @test
     */
    public function it_can_store_parsed_viewer_ip_as_request_attribute()
    {
        app('config')->set('cloudfront-proxies.viewer-address-attribute', 'cloudfront_viewer_ip');

        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            [
                'HTTP_CLOUDFRONT_VIEWER_ADDRESS' => '[2600:1f18:abcd::1234]:443',
            ],
            null
        );

        $middleware = new CloudfrontProxies;
        $mock = Mockery::mock(Guzzle::class);
        $response = new Response(200, [
            'Content-Type' => 'application/json'
        ], json_encode([
            'prefixes' => [
                [
                    'ip_prefix' => '127.0.0.1/16',
                    'region' => 'GLOBAL',
                    'service' => 'CLOUDFRONT'
                ]
            ]
        ]));

        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn($response);
        app()->instance(Guzzle::class, $mock);

        $middleware->handle($request, function () {
        });

        $this->assertEquals('2600:1f18:abcd::1234', $request->attributes->get('cloudfront_viewer_ip'));
        $this->assertNull($request->header('x-real-ip'));
    }

    /**
     * @test
     */
    public function it_sets_client_ip_from_cloudfront_viewer_address()
    {
        $request = new Request(
            [],
            [],
            [],
            [],
            [],
            [
                'HTTP_CLOUDFRONT_VIEWER_ADDRESS' => '198.51.100.25:443',
                'HTTP_X_FORWARDED_FOR' => '54.240.143.10',
                'REMOTE_ADDR' => '127.0.0.1',
            ],
            null
        );

        $middleware = new CloudfrontProxies;
        $mock = Mockery::mock(Guzzle::class);
        $response = new Response(200, [
            'Content-Type' => 'application/json'
        ], json_encode([
            'prefixes' => [
                [
                    'ip_prefix' => '127.0.0.1/16',
                    'region' => 'GLOBAL',
                    'service' => 'CLOUDFRONT'
                ]
            ]
        ]));

        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn($response);
        app()->instance(Guzzle::class, $mock);

        $middleware->handle($request, function () {
        });

        $this->assertEquals('198.51.100.25', $request->getClientIp());
        $this->assertEquals('198.51.100.25', $request->header('x-forwarded-for'));
    }
}

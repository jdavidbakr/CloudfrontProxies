<?php

use Illuminate\Http\Request;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Routing\UrlGenerator;
use Psr\Http\Message\ResponseInterface;
use Orchestra\Testbench\TestCase as BaseTestCase;
use jdavidbakr\CloudfrontProxies\CloudfrontProxies;

class CloudfrontProxiesTest extends BaseTestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('cloudfront-proxies.ip-range-data-url', 'https://ip-ranges.amazonaws.com/ip-ranges.json');
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
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')
            ->andReturn(json_encode([
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
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')
            ->andReturn(json_encode([
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
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')
            ->andReturn(json_encode([
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
        $response = Mockery::mock(ResponseInterface::class);
        $response->shouldReceive('getBody')
            ->andReturn(json_encode([
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
}

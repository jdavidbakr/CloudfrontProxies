<?php

use Orchestra\Testbench\TestCase as BaseTestCase;
use jdavidbakr\CloudfrontProxies\CloudfrontProxies;
use Illuminate\Http\Request;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Routing\UrlGenerator;

class CloudfrontProxiesTest extends BaseTestCase
{

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
        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn(Mockery::mock([
                'getBody' => json_encode([
                    'prefixes' => [
                        [
                            'ip_prefix' => '127.0.0.1/16',
                            'region' => 'GLOBAL',
                            'service' => 'CLOUDFRONT'
                        ]
                    ]
                ])
            ]));
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
        $request->setTrustedProxies(['123.45.67/8'], Request::HEADER_X_FORWARDED_ALL);
        $middleware = new CloudfrontProxies;
        $mock = Mockery::mock(Guzzle::class);
        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn(Mockery::mock([
                'getBody' => json_encode([
                    'prefixes' => [
                        [
                            'ip_prefix' => '127.0.0.1/16',
                            'region' => 'GLOBAL',
                            'service' => 'CLOUDFRONT'
                        ]
                    ]
                ])
            ]));
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
        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn(Mockery::mock([
                'getBody' => json_encode([
                    'prefixes' => [
                        [
                            'ip_prefix' => '127.0.0.1/16',
                            'region' => 'GLOBAL',
                            'service' => 'CLOUDFRONT'
                        ]
                    ]
                ])
            ]));
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
        $mock->shouldReceive('get')
            ->with('https://ip-ranges.amazonaws.com/ip-ranges.json')
            ->once()
            ->andReturn(Mockery::mock([
                'getBody' => json_encode([
                    'prefixes' => [
                        [
                            'ip_prefix' => '127.0.0.1/16',
                            'region' => 'GLOBAL',
                            'service' => 'CLOUDFRONT'
                        ]
                    ]
                ])
            ]));
        app()->instance(Guzzle::class, $mock);
        $routes = app('router')->getRoutes();
        $url = new UrlGenerator($routes, $request);

        $middleware->handle($request, function () {
        });

        $this->assertEquals('https://localhost', $url->to('/'));
    }
}

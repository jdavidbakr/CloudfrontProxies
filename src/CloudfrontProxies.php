<?php

namespace jdavidbakr\CloudfrontProxies;

use Closure;
use Cache;
use GuzzleHttp\Client as Guzzle;
use Illuminate\Http\Request;

class CloudfrontProxies
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($this->isCloudfrontRequest($request)) {
            $this->loadTrustedProxies($request);
            $this->setCloudfrontHeaders($request);
            $this->setViewerForwardedForHeader($request);
            $this->setViewerAddressAttribute($request);
        }
        return $next($request);
    }

    /**
     * Determine whether this request likely came through CloudFront.
     *
     * We support both legacy forwarded headers and CloudFront-specific IDs.
     */
    protected function isCloudfrontRequest($request)
    {
        return (bool) (
            $request->header('cloudfront-forwarded-proto') ||
            $request->header('cloudfront-forwarded-port') ||
            $request->header('cloudfront-viewer-address') ||
            $request->header('x-amz-cf-id')
        );
    }

    /**
     * If this is a cloudfront request, load up the trusted proxies
     */
    protected function loadTrustedProxies($request)
    {
        // Get the CloudFront IP addresses
        $proxies = Cache::remember('cloudfront-proxy-ip-addresses', now()->addHour(), function () {
            $ip_range_data = config('cloudfront-proxies.ip-range-data-url');
            $client = app()->make(Guzzle::class);
            $res = $client->get($ip_range_data);
            $payload = json_decode($res->getBody(), true);

            $ipv4 = collect($payload['prefixes'] ?? [])
                ->filter(function ($address) {
                    return ($address['service'] ?? null) == 'CLOUDFRONT';
                })
                ->pluck('ip_prefix');

            $ipv6 = collect($payload['ipv6_prefixes'] ?? [])
                ->filter(function ($address) {
                    return ($address['service'] ?? null) == 'CLOUDFRONT';
                })
                ->pluck('ipv6_prefix');

            return $ipv4->merge($ipv6)->values()->all();
        });
        $request->setTrustedProxies(array_merge($request->getTrustedProxies(), $proxies), config('cloudfront-proxies.trust-proxies-headers'));
    }

    protected function setCloudfrontHeaders($request)
    {
        $headers = $request->headers;
        if($request->header('cloudfront-forwarded-proto')) {
            $headers->add(['x-forwarded-proto' => $headers->get('cloudfront-forwarded-proto')]);
        }
        if($request->header('cloudfront-forwarded-port')) {
            $headers->add(['x-forwarded-port' => $headers->get('cloudfront-forwarded-port')]);
        }
    }

    /**
     * Map CloudFront-Viewer-Address to X-Forwarded-For so Request::clientIp()
     * resolves to the viewer address when trusted proxies are configured.
     */
    protected function setViewerForwardedForHeader($request)
    {
        if (!config('cloudfront-proxies.viewer-address-to-forwarded-for', true)) {
            return;
        }

        $viewerAddress = $request->header('cloudfront-viewer-address');
        if (!$viewerAddress) {
            return;
        }

        $parsedIp = $this->parseViewerIp($viewerAddress);
        if (!$parsedIp) {
            return;
        }

        $request->headers->set('x-forwarded-for', $parsedIp);
    }

    /**
     * Optionally store parsed viewer IP from CloudFront-Viewer-Address.
     */
    protected function setViewerAddressAttribute($request)
    {
        $attribute = config('cloudfront-proxies.viewer-address-attribute');
        if (!$attribute) {
            return;
        }

        $viewerAddress = $request->header('cloudfront-viewer-address');
        if (!$viewerAddress) {
            return;
        }

        $parsedIp = $this->parseViewerIp($viewerAddress);
        if ($parsedIp) {
            $request->attributes->set($attribute, $parsedIp);
        }
    }

    /**
     * Parse CloudFront-Viewer-Address values like:
     * - 198.51.100.7:443
     * - [2001:db8::1]:443
     */
    protected function parseViewerIp($viewerAddress)
    {
        $value = trim((string) $viewerAddress);

        if (preg_match('/^\[(.*)\](?::\d+)?$/', $value, $matches) === 1 && filter_var($matches[1], FILTER_VALIDATE_IP)) {
            return $matches[1];
        }

        if (filter_var($value, FILTER_VALIDATE_IP)) {
            return $value;
        }

        $lastColon = strrpos($value, ':');
        if ($lastColon === false) {
            return null;
        }

        $ipPart = substr($value, 0, $lastColon);
        if (filter_var($ipPart, FILTER_VALIDATE_IP)) {
            return $ipPart;
        }

        return null;
    }
}

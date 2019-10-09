# Cloudfront Proxies

[![Latest Version on Packagist][ico-version]][link-packagist]
[![Total Downloads][ico-downloads]][link-downloads]
[![Travis][ico-travis]][link-travis]

## Purpose

One of the great things about putting your application behind a load balancer or CDN is that you can terminate your TLS there, and make the requests to your application via http. The problem with this, though, is that your application is not aware of the protocol with which it is being accessed. This will cause a problem with Laravel's URL generation tools, as the assets will be prefixed with http.

Laravel takes care of this nicely by using the TrustedProxies package, which allows you to define what IP addresses and what headers you want to use to convert the incoming request to the IP address and protocol of the originating request.

This was all wonderful, until Laravel 5.6 came out. This version of Laravel uses Symfony version 4, which no longer exposes the header you want to use to determine the protocol. Not a problem, you say, because you can use the X-Forwarded headers? It wouldn't be a problem, except for the fact that CloudFront uses a special header `Cloudfront-Forwarded-Proto` - and so now there is not a simple solution to set the protocol.

Further, you probably don't want to expose all IP addresses to your trusted proxy settings - ideally we should only use CloudFront IP addresses for our trusted proxies.

## The solution

This package contains a simple middleware that does two very important tasks:

1. Downloads the CloudFront IP addresses into the trusted proxy IP addresses. This is cached according to your cache settings for one hour, so you are not making this call on every request.
2. Adds the `X-Forwarded-Proto` header to your requests based on the `Cloudfront-Forwarded-Proto` value. This helps Symfony behave as if the original headers were what it needed in the first place.

This middleware only fires if the `Cloudfront-Forwarded-Proto` header exists in the incoming headers, so it is ignored if you are using other load balancers or accessing the server directly.  Note that CloudFront does not send this header by default - it must be explicitly whitelisted.  (See the CloudFront documentation for more information on sending headers and cookies)

## Usage

To use, simply install via composer:

```
composer require jdavidbakr/cloudfront-proxies
```

Then add the middleware to your kernel after the `TrustProxies` middleware:

```
        \App\Http\Middleware\TrustProxies::class,
        \jdavidbakr\CloudfrontProxies\CloudfrontProxies::class,
```

And everything should be good to go from here.

[ico-version]: https://img.shields.io/packagist/v/jdavidbakr/cloudfront-proxies.svg?style=flat-square
[ico-license]: https://img.shields.io/badge/license-MIT-brightgreen.svg?style=flat-square
[ico-travis]: https://img.shields.io/travis/jdavidbakr/cloudfrontproxies/master.svg?style=flat-square
[ico-scrutinizer]: https://img.shields.io/scrutinizer/coverage/g/jdavidbakr/cloudfrontproxies.svg?style=flat-square
[ico-code-quality]: https://img.shields.io/scrutinizer/g/jdavidbakr/cloudfrontproxies.svg?style=flat-square
[ico-downloads]: https://img.shields.io/packagist/dt/jdavidbakr/cloudfront-proxies.svg?style=flat-square
[link-packagist]: https://packagist.org/packages/jdavidbakr/cloudfront-proxies
[link-travis]: https://travis-ci.org/jdavidbakr/cloudfrontproxies
[link-scrutinizer]: https://scrutinizer-ci.com/g/jdavidbakr/cloudfrontproxies/code-structure
[link-code-quality]: https://scrutinizer-ci.com/g/jdavidbakr/cloudfrontproxies
[link-downloads]: https://packagist.org/packages/jdavidbakr/cloudfront-proxies
[link-author]: https://github.com/jdavidbakr
[link-contributors]: ../../contributors

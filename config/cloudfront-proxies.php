<?php

use Illuminate\Http\Request;

return [
    /**
     * URL to pull the ip ranges from amazon
     */
    'ip-range-data-url' => 'https://ip-ranges.amazonaws.com/ip-ranges.json',
    
    /**
     * Headers defined in the TrustProxies class
     */
    'trust-proxies-headers' => Request::HEADER_X_FORWARDED_ALL,
];

<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustHosts as Middleware;

class TrustHosts extends Middleware
{
    /**
     * Get the host patterns that should be trusted.
     *
     * @return array
     */
    public function hosts()
    {
        $hosts = [$this->allSubdomainsOfApplicationUrl()];
        // Explicitly allow APP_URL host (e.g. 192.168.1.184) for LAN access from other machines
        $appHost = parse_url(config('app.url'), PHP_URL_HOST);
        if ($appHost) {
            $hosts[] = '^' . preg_quote($appHost, '^') . '$';
        }
        return $hosts;
    }
}

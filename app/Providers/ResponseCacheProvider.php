<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Cache;

class ResponseCacheProvider extends ServiceProvider
{
    public function boot()
    {
        // Macro for cached responses
        Response::macro('cached', function ($key, $callback, $ttl = 300) {
            if (Cache::has($key)) {
                $cached = Cache::get($key);
                return response($cached['content'])
                    ->withHeaders($cached['headers'])
                    ->header('X-Cache', 'HIT');
            }

            $response = $callback();
            
            if ($response->isSuccessful()) {
                Cache::put($key, [
                    'content' => $response->getContent(),
                    'headers' => [
                        'Content-Type' => $response->headers->get('Content-Type') ?? 'application/json',
                    ]
                ], now()->addSeconds($ttl));
                
                $response->header('X-Cache', 'MISS');
            }
            
            return $response;
        });
    }
}
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CacheResponse
{
    /**
     * Cache duration in minutes
     */
    protected $cacheDuration = 5;

    /**
     * Routes to exclude from caching (wildcards supported)
     */
    protected $excludeRoutes = [
        // Auth routes
        'api/auth/*',
        'api/login',
        'api/register',
        'api/logout',
        
        // Cache management
        'api/cache/*',
        
        // Admin routes (if any)
        'api/admin/*',
        
        // POST, PUT, DELETE methods are automatically skipped
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next)
    {
        // Skip non-GET requests
        if (!$request->isMethod('get')) {
            return $next($request);
        }

        // Skip excluded routes
        if ($this->shouldSkipCache($request)) {
            return $next($request);
        }

        // Skip authenticated requests (user-specific data)
        if ($request->user()) {
            return $next($request);
        }

        // Skip if cache-busting parameter is present
        if ($request->has('_nocache')) {
            return $next($request);
        }

        // Generate cache key
        $cacheKey = $this->generateCacheKey($request);

        // Check if cached response exists
        if (Cache::has($cacheKey)) {
            $cachedResponse = Cache::get($cacheKey);
            
            $response = response($cachedResponse['content'])
                ->withHeaders($cachedResponse['headers']);
            
            // Add cache hit headers
            $response->headers->set('X-Cache', 'HIT');
            $response->headers->set('X-Cache-Time', now()->toDateTimeString());
            $response->headers->set('X-Cache-Expires', now()->addMinutes($this->cacheDuration)->toDateTimeString());
            
            return $response;
        }

        // Get response
        $response = $next($request);

        // Only cache successful responses
        if ($response->isSuccessful() && $response->getStatusCode() === 200) {
            $cacheData = [
                'content' => $response->getContent(),
                'headers' => $this->getCacheableHeaders($response),
            ];

            Cache::put($cacheKey, $cacheData, now()->addMinutes($this->cacheDuration));
            
            // Add cache miss headers
            $response->headers->set('X-Cache', 'MISS');
            $response->headers->set('X-Cache-Stored', now()->toDateTimeString());
            $response->headers->set('X-Cache-Expires', now()->addMinutes($this->cacheDuration)->toDateTimeString());
        }

        return $response;
    }

    /**
     * Check if request should skip cache
     */
    protected function shouldSkipCache(Request $request)
    {
        $path = $request->path();
        
        // Check against excluded routes (with wildcard support)
        foreach ($this->excludeRoutes as $exclude) {
            // Convert wildcard to regex and escape special characters
            $pattern = '#^' . str_replace('*', '.*', preg_quote($exclude, '#')) . '$#';
            
            if (preg_match($pattern, $path)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate cache key for request
     */
    protected function generateCacheKey(Request $request)
    {
        // Include route name, path, query parameters, and language if applicable
        $routeName = $request->route() ? $request->route()->getName() : '';
        $path = $request->path();
        $query = http_build_query($request->query());
        $locale = app()->getLocale() ?? 'en';
        
        $key = 'response_cache_' . md5($routeName . $path . $query . $locale);
        
        return $key;
    }

    /**
     * Get cacheable headers
     */
    protected function getCacheableHeaders($response)
    {
        $allowedHeaders = [
            'Content-Type',
            'Content-Length',
            'Content-Encoding',
        ];
        
        $headers = [];
        foreach ($allowedHeaders as $header) {
            if ($response->headers->has($header)) {
                $headers[$header] = $response->headers->get($header);
            }
        }
        
        // Add cache control headers
        $headers['Cache-Control'] = 'public, max-age=' . ($this->cacheDuration * 60);
        $headers['X-Cache-Duration'] = $this->cacheDuration . ' minutes';
        
        return $headers;
    }

    /**
     * Clear cache for specific route
     */
    public static function clearRouteCache($route)
    {
        $cacheKey = 'response_cache_' . md5($route);
        Cache::forget($cacheKey);
    }

    /**
     * Clear all response cache
     */
    public static function clearAllCache()
    {
        // For Redis
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            $redis = Cache::getStore()->connection();
            $keys = $redis->keys(config('cache.prefix') . ':response_cache_*');
            foreach ($keys as $key) {
                Cache::forget(str_replace(config('cache.prefix') . ':', '', $key));
            }
        } else {
            // For file/database drivers
            Cache::flush();
        }
    }
}
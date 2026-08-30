<?php

namespace App\Http\Controllers;

use App\Http\Middleware\CacheResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CacheController extends Controller
{
    /**
     * Clear all route cache
     */
    public function clear()
    {
        // Clear all cache keys
        $keys = [
            'route_cache_*',
            'weather_data_*',
            'all_cities_weather_data',
            'comfort_summary',
            'cities_list_data',
            'city_rank_*',
        ];
        
        foreach ($keys as $pattern) {
            if (str_contains($pattern, '*')) {
                // For Redis, use scan
                if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                    $redis = Cache::getStore()->connection();
                    $cursor = null;
                    $patternKey = config('cache.prefix') . ':' . $pattern;
                    do {
                        $keys = $redis->scan($cursor, ['match' => $patternKey]);
                        foreach ($keys as $key) {
                            Cache::forget(str_replace(config('cache.prefix') . ':', '', $key));
                        }
                    } while ($cursor > 0);
                }
            } else {
                Cache::forget($pattern);
            }
        }
        
        // Clear route cache keys
        Cache::forget('route_cache_keys');
        
        return response()->json([
            'success' => true,
            'message' => 'All cache cleared successfully',
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * Get cache status
     */
    public function status()
    {
        $status = [
            'driver' => config('cache.default'),
            'prefix' => config('cache.prefix'),
            'cache_status' => [
                'route_cache' => $this->hasCache('route_cache_*'),
                'weather_cache' => $this->hasCache('weather_data_*'),
                'cities_cache' => Cache::has('cities_list_data'),
                'summary_cache' => Cache::has('comfort_summary'),
                'all_cities_cache' => Cache::has('all_cities_weather_data'),
            ],
            'timestamp' => now()->toDateTimeString(),
        ];
        
        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }

    /**
     * Check if cache exists with pattern
     */
    private function hasCache($pattern)
    {
        if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
            $redis = Cache::getStore()->connection();
            $keys = $redis->keys(config('cache.prefix') . ':' . $pattern);
            return count($keys) > 0;
        }
        
        // For file/database drivers, check specific keys
        return Cache::has(str_replace('*', '', $pattern));
    }

    /**
     * Clear specific route cache
     */
    public function clearRoute(Request $request)
    {
        $route = $request->input('route');
        
        if ($route) {
            $cacheKey = 'route_cache_' . md5($route);
            Cache::forget($cacheKey);
            
            return response()->json([
                'success' => true,
                'message' => "Cache cleared for route: {$route}"
            ]);
        }
        
        return response()->json([
            'success' => false,
            'message' => 'Route parameter required'
        ], 400);
    }
}
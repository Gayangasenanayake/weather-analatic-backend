<?php

namespace App\Http\Controllers;

use App\Services\WeatherService;
use Illuminate\Http\Request;

class CityController extends Controller
{
    protected $weatherService;

    public function __construct(WeatherService $weatherService)
    {
        $this->weatherService = $weatherService;
    }

    /**
     * Get all cities from the JSON file
     */
    public function index()
    {
        try {
            $cities = $this->weatherService->getAllCities();
            
            if (request()->filled('search')) {
                $searchTerm = strtolower(trim(request()->search));
                $cities = array_filter($cities, function ($city) use ($searchTerm) {
                    return strpos(strtolower($city['CityName'] ?? ''), $searchTerm) !== false;
                });
                // Re-index array
                $cities = array_values($cities);
            }
            
            return response()->json([
                'success' => true,
                'total' => count($cities),
                'search' => request()->search ?? null,
                'data' => $cities
            ]);
            
        
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get a specific city by code
     */
    public function show($cityCode)
    {
        try {
            $city = $this->weatherService->getCityByCode($cityCode);
            
            if ($city) {
                return response()->json([
                    'success' => true,
                    'data' => $city
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'City not found with code: ' . $cityCode
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search cities by name
     */
    public function search(Request $request)
    {
        try {
            $request->validate([
                'q' => 'required|string|min:2'
            ]);

            $searchTerm = $request->input('q');
            $cities = $this->weatherService->searchCities($searchTerm);
            
            // Re-index array for JSON
            $cities = array_values($cities);

            return response()->json([
                'success' => true,
                'query' => $searchTerm,
                'total' => count($cities),
                'data' => $cities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cities with their current weather data
     */
    public function withWeather()
    {
        try {
            $cities = $this->weatherService->getCitiesWithWeather();
            
            return response()->json([
                'success' => true,
                'total' => count($cities),
                'data' => $cities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get paginated list of cities
     */
    public function paginated(Request $request)
    {
        try {
            $perPage = $request->input('per_page', 10);
            $page = $request->input('page', 1);
            
            $allCities = $this->weatherService->getAllCities();
            $total = count($allCities);
            
            // Calculate pagination
            $offset = ($page - 1) * $perPage;
            $paginated = array_slice($allCities, $offset, $perPage);
            
            return response()->json([
                'success' => true,
                'data' => $paginated,
                'pagination' => [
                    'current_page' => (int)$page,
                    'per_page' => (int)$perPage,
                    'total' => $total,
                    'last_page' => ceil($total / $perPage)
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get cities grouped by status (from cached data)
     */
    public function groupByStatus()
    {
        try {
            $cities = $this->weatherService->getAllCities();
            $grouped = [];
            
            foreach ($cities as $city) {
                $status = $city['Status'] ?? 'Unknown';
                if (!isset($grouped[$status])) {
                    $grouped[$status] = [];
                }
                $grouped[$status][] = $city;
            }
            
            // Sort statuses alphabetically
            ksort($grouped);
            
            return response()->json([
                'success' => true,
                'data' => $grouped,
                'summary' => array_map('count', $grouped)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get city statistics
     */
    public function statistics()
    {
        try {
            $cities = $this->weatherService->getAllCities();
            
            // Count by status
            $statusCount = [];
            $temperatures = [];
            
            foreach ($cities as $city) {
                $status = $city['Status'] ?? 'Unknown';
                $statusCount[$status] = ($statusCount[$status] ?? 0) + 1;
                
                if (isset($city['Temp'])) {
                    $temperatures[] = (float)$city['Temp'];
                }
            }
            
            // Sort statuses by count (descending)
            arsort($statusCount);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'total_cities' => count($cities),
                    'unique_statuses' => count($statusCount),
                    'status_distribution' => $statusCount,
                    'temperature_stats' => !empty($temperatures) ? [
                        'min' => min($temperatures),
                        'max' => max($temperatures),
                        'average' => round(array_sum($temperatures) / count($temperatures), 1),
                        'count' => count($temperatures)
                    ] : null
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
 * Get cities sorted by comfort score
 */
    public function sortedByComfort()
    {
        try {
            $cities = $this->weatherService->getSortedCitiesByComfort();
            
            return response()->json([
                'success' => true,
                'data' => $cities
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
        ], 500);
    }
}
}
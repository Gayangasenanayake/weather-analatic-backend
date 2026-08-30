<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    protected $comfortIndexService;
    protected $apiKey;
    protected $baseUrl = 'https://api.openweathermap.org/data/2.5';
    protected $citiesList = [];


    public function __construct()
    {
        $this->apiKey = config('services.openweather.api_key');
        $this->comfortIndexService = new ComfortIndexService();
        $this->loadCitiesList();

    }

    private function loadCitiesList($citiesPath = null)
    {
        if (!$citiesPath) {
            $citiesPath = resource_path('cities.json');
        }

        if (!file_exists($citiesPath)) {
            return;
        }

        $content = file_get_contents($citiesPath);
        $citiesData = json_decode($content, true);

        if (isset($citiesData['List']) && is_array($citiesData['List'])) {
            $this->citiesList = $citiesData['List'];
        }
    }

 /**
     * Get all cities from the JSON file
     */
    public function getAllCities($citiesPath = null)
    {
        if (!$citiesPath) {
            $citiesPath = resource_path('cities.json');
        }

        if (!file_exists($citiesPath)) {
            throw new \Exception('Cities JSON file not found');
        }

        $content = file_get_contents($citiesPath);
        $citiesData = json_decode($content, true);

        if (!isset($citiesData['List']) || !is_array($citiesData['List'])) {
            throw new \Exception('Invalid cities JSON format');
        }

        return $citiesData['List'];
    }

    /**
     * Get a specific city by city code
     */
    public function getCityByCode($cityCode, $citiesPath = null)
    {
        $cities = $this->getAllCities($citiesPath);
        
        foreach ($cities as $city) {
            if ($city['CityCode'] == $cityCode) {
                return $city;
            }
        }
        
        return null;
    }


    private function fetchAllCitiesWeatherForRanking()
    {
        $results = [];
        
        foreach ($this->citiesList as $city) {
            $cityCode = $city['CityCode'] ?? null;
            
            if ($cityCode) {
                $weatherData = $this->fetchWeatherByCityCode($cityCode);
                
                if ($weatherData) {
                    $temp = $weatherData['main']['temp'] ?? 20;
                    $humidity = $weatherData['main']['humidity'] ?? 50;
                    $windSpeed = isset($weatherData['wind']['speed']) ? $weatherData['wind']['speed'] * 3.6 : 10;
                    $condition = $weatherData['weather'][0]['description'] ?? 'clear';
                    
                    $comfortIndex = $this->comfortIndexService->calculateComfortIndex(
                        $temp, 
                        $humidity, 
                        $windSpeed, 
                        $condition
                    );
                    
                    $results[] = [
                        'city_code' => $cityCode,
                        'city_name' => $weatherData['name'] ?? 'Unknown',
                        'comfort_score' => $comfortIndex['score']
                    ];
                }
            }
        }
        
        return $results;
    }

    /**
 * Get city rank based on comfort score (highest score = rank 1)
 * Returns only the rank number as integer
 */
public function getCityRankByComfort($cityCode)
    {
        // Get all cities with weather data (without calling this method again)
        $allCities = $this->fetchAllCitiesWeatherForRanking();
        
        if (empty($allCities)) {
            return null;
        }
        
        // Sort by comfort score (highest first)
        usort($allCities, function ($a, $b) {
            return $b['comfort_score'] <=> $a['comfort_score'];
        });
        
        // Find the city and return its rank
        foreach ($allCities as $index => $city) {
            if ($city['city_code'] == $cityCode) {
                return $index + 1;
            }
        }
        
        return null;
    }


/**
 * Helper: Get city name by code
 */
private function getCityNameByCode($cityCode)
{
    foreach ($this->citiesList as $city) {
        if ($city['CityCode'] == $cityCode) {
            return $city['CityName'];
        }
    }
    return null;
}

    /**
     * Search cities by name (partial match)
     */
    public function searchCities($searchTerm, $citiesPath = null)
    {
        $cities = $this->getAllCities($citiesPath);
        $searchTerm = strtolower($searchTerm);
        
        return array_filter($cities, function ($city) use ($searchTerm) {
            return strpos(strtolower($city['CityName']), $searchTerm) !== false;
        });
    }
    
    public function fetchWeatherByCityCode($cityCode)
    {
        try {
            $response = Http::get("{$this->baseUrl}/weather", [
                'id' => $cityCode,
                'appid' => $this->apiKey,
                'units' => 'metric' // Use 'imperial' for Fahrenheit
            ]);

            if ($response->successful()) {
                return $response->json();
            } else {
                Log::error('Weather API Error for city code ' . $cityCode . ': ' . $response->body());
                return null;
            }
        } catch (\Exception $e) {
            Log::error('Weather API Exception for city code ' . $cityCode . ': ' . $e->getMessage());
            return null;
        }
    }

    
    public function fetchAllCitiesWeather($citiesPath = null)
    {
        if (!$citiesPath) {
            $citiesPath = resource_path('cities.json');
        }

        if (!file_exists($citiesPath)) {

            throw new \Exception('Cities JSON file not found');
        }

        $content = file_get_contents($citiesPath);
        $citiesData = json_decode($content, true);

        if (!isset($citiesData['List']) || !is_array($citiesData['List'])) {
            throw new \Exception('Invalid cities JSON format');
        }

        $results = [];

        foreach ($citiesData['List'] as $city) {
            $cityCode = $city['CityCode'] ?? null;
            $cityName = $city['CityName'] ?? 'Unknown';

            if ($cityCode) {
                $weatherData = $this->fetchWeatherByCityCode($cityCode);
                
                if ($weatherData) {
                    $results[] = [
                        'city_name' => $cityName,
                        'city_code' => $cityCode,
                        'weather' => $weatherData,
                        'temperature' => $weatherData['main']['temp'] ?? null,
                        'status' => $weatherData['weather'][0]['description'] ?? 'Unknown',
                    ];
                }
            }
        }

        return $results;
    }

   
    public function parseWeatherData($weatherData)
    {
        if (!$weatherData) return null;

        return [
            'city_name' => $weatherData['name'] ?? 'Unknown',
            'country' => $weatherData['sys']['country'] ?? 'Unknown',
            'temperature' => $weatherData['main']['temp'] ?? null,
            'feels_like' => $weatherData['main']['feels_like'] ?? null,
            'humidity' => $weatherData['main']['humidity'] ?? null,
            'pressure' => $weatherData['main']['pressure'] ?? null,
            'weather_description' => $weatherData['weather'][0]['description'] ?? 'Unknown',
            'weather_icon' => $weatherData['weather'][0]['icon'] ?? null,
            'wind_speed' => $weatherData['wind']['speed'] ?? null,
            'wind_direction' => $weatherData['wind']['deg'] ?? null,
            'clouds' => $weatherData['clouds']['all'] ?? null,
            'sunrise' => isset($weatherData['sys']['sunrise']) ? date('H:i:s', $weatherData['sys']['sunrise']) : null,
            'sunset' => isset($weatherData['sys']['sunset']) ? date('H:i:s', $weatherData['sys']['sunset']) : null,
        ];
    }

    /**
     * Fetch weather data with comfort index for a specific city
     */
    public function fetchWeatherWithComfortIndex($cityCode)
    {
        $weatherData = $this->fetchWeatherByCityCode($cityCode);
        
        if (!$weatherData) {
            return null;
        }
        
        // Extract needed data
        $temp = $weatherData['main']['temp'] ?? 20;
        $humidity = $weatherData['main']['humidity'] ?? 50;
        $windSpeed = isset($weatherData['wind']['speed']) ? $weatherData['wind']['speed'] * 3.6 : 10; // Convert m/s to km/h
        $condition = $weatherData['weather'][0]['description'] ?? 'clear';
        
        // Calculate comfort index
        $comfortIndex = $this->comfortIndexService->calculateComfortIndex(
            $temp, 
            $humidity, 
            $windSpeed, 
            $condition
        );

        $rank = $this->getCityRankByComfort($cityCode);
       return [
            'city_name' => $weatherData['name'] ?? 'Unknown',
            'country' => $weatherData['sys']['country'] ?? 'Unknown',
            'temperature' => $temp,
            'humidity' => $humidity,
            'wind_speed' => round($windSpeed, 1),
            'weather_condition' => $condition,
            'comfort_index' => $comfortIndex,
            'ranking' => $rank,
            'raw_weather_data' => $weatherData // Optional: include full data if needed
        ];
    }


    /**
     * Fetch weather with comfort index for all cities
     */
    public function fetchAllCitiesWeatherWithComfortIndex($citiesPath = null)
    {
        if (!$citiesPath) {
            $citiesPath = resource_path('cities.json');
        }

        if (!file_exists($citiesPath)) {
            throw new \Exception('Cities JSON file not found');
        }

        $content = file_get_contents($citiesPath);
        $citiesData = json_decode($content, true);

        if (!isset($citiesData['List']) || !is_array($citiesData['List'])) {
            throw new \Exception('Invalid cities JSON format');
        }

        $results = [];

        foreach ($citiesData['List'] as $city) {
            $cityCode = $city['CityCode'] ?? null;
            
            if ($cityCode) {
                $weatherData = $this->fetchWeatherWithComfortIndex($cityCode);
                
                if ($weatherData) {
                    $results[] = $weatherData;
                }
            }
        }

        // Sort by comfort score (highest first)
        usort($results, function ($a, $b) {
            return $b['comfort_index']['score'] <=> $a['comfort_index']['score'];
        });

        return $results;
    }

    /**
     * Get summary statistics for all cities
     */
    public function getComfortSummary($citiesData)
    {
        if (empty($citiesData)) {
            return null;
        }
        
        $scores = array_column(array_column($citiesData, 'comfort_index'), 'score');
        
        return [
            'total_cities' => count($citiesData),
            'average_score' => round(array_sum($scores) / count($scores), 1),
            'highest_score' => max($scores),
            'lowest_score' => min($scores),
            'best_city' => $citiesData[array_search(max($scores), $scores)]['city_name'],
            'worst_city' => $citiesData[array_search(min($scores), $scores)]['city_name'],
            'score_distribution' => $this->getScoreDistribution($scores)
        ];
    }

    /**
     * Get distribution of comfort scores
     */
    private function getScoreDistribution($scores)
    {
        $distribution = [
            'excellent' => 0, // 85-100
            'good' => 0,      // 70-84
            'moderate' => 0,  // 55-69
            'fair' => 0,      // 40-54
            'poor' => 0,      // 25-39
            'very_poor' => 0  // 0-24
        ];
        
        foreach ($scores as $score) {
            if ($score >= 85) $distribution['excellent']++;
            elseif ($score >= 70) $distribution['good']++;
            elseif ($score >= 55) $distribution['moderate']++;
            elseif ($score >= 40) $distribution['fair']++;
            elseif ($score >= 25) $distribution['poor']++;
            else $distribution['very_poor']++;
        }
        
        return $distribution;
    }

    /**
 * Get cities sorted by comfort score (highest first)
 */
/**
 * Get cities sorted by comfort score - Without rank
 */
public function getSortedCitiesByComfort()
{
    $cities = $this->fetchAllCitiesWeatherWithComfortIndex();
    
    usort($cities, function ($a, $b) {
        return $b['comfort_index']['score'] <=> $a['comfort_index']['score'];
    });
    
    // Return only city name and score (no rank)
    return array_map(function ($city) {
        return [
            'city' => $city['city_name'],
            'score' => $city['comfort_index']['score']
        ];
    }, $cities);
}


}
<?php


// namespace App\Http\Controllers;

// use App\Services\WeatherService;
// use Illuminate\Http\Request;

// class WeatherReadingController extends Controller
// {
//     protected $weatherService;

//     public function __construct(WeatherService $weatherService)
//     {
//         $this->weatherService = $weatherService;
//     }

    
    // public function index()
    // {
    //     try {
    //         $weatherData = $this->weatherService->fetchAllCitiesWeather();
            
    //         return response()->json([
    //             'success' => true,
    //             'data' => $weatherData
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    
    // public function show($cityCode)
    // {
    //     try {
    //         $weatherData = $this->weatherService->fetchWeatherByCityCode($cityCode);
            
    //         if ($weatherData) {
    //             return response()->json([
    //                 'success' => true,
    //                 'data' => $this->weatherService->parseWeatherData($weatherData)
    //             ]);
    //         }

    //         return response()->json([
    //             'success' => false,
    //             'message' => 'Weather data not found for city code: ' . $cityCode
    //         ], 404);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

    
    // public function updateAll()
    // {
    //     try {
    //         $weatherData = $this->weatherService->fetchAllCitiesWeather();
            
            
    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Weather data updated successfully',
    //             'data' => $weatherData
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }

   
    // public function getCachedWeather()
    // {
    //     try {
    //         $weatherData = cache()->remember('all_cities_weather', 3600, function () {
    //             return $this->weatherService->fetchAllCitiesWeather();
    //         });

    //         return response()->json([
    //             'success' => true,
    //             'data' => $weatherData
    //         ]);
    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }
// }

namespace App\Http\Controllers;

use App\Services\WeatherService;
use Illuminate\Http\Request;

class WeatherReadingController extends Controller
{
    protected $weatherService;

    public function __construct(WeatherService $weatherService)
    {
        $this->weatherService = $weatherService;
    }

    /**
     * Get all cities weather data with comfort index
     */
    public function index()
    {
        try {
            $weatherData = $this->weatherService->fetchAllCitiesWeatherWithComfortIndex();
            $summary = $this->weatherService->getComfortSummary($weatherData);
            
            return response()->json([
                'success' => true,
                'data' => $weatherData,
                'summary' => $summary
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get weather for a specific city by code with comfort index
     */
    public function show($cityCode)
    {
        try {
            $weatherData = $this->weatherService->fetchWeatherWithComfortIndex($cityCode);
            if ($weatherData) {
                return response()->json([
                    'success' => true,
                    'data' => $weatherData
                ])
                ->header('Cache-Control', 'public, max-age=300') // 5 minutes
                ->header('X-Cache-Duration', '5 minutes');
            }

            return response()->json([
                'success' => false,
                'message' => 'Weather data not found for city code: ' . $cityCode
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get top 5 most comfortable cities
     */
    public function topComfortable()
    {
        try {
            $weatherData = $this->weatherService->fetchAllCitiesWeatherWithComfortIndex();
            $top5 = array_slice($weatherData, 0, 5);
            
            return response()->json([
                'success' => true,
                'data' => $top5,
                'message' => 'Top 5 most comfortable cities'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get weather with detailed penalty explanation
     */
    public function detailed($cityCode)
    {
        try {
            $weatherData = $this->weatherService->fetchWeatherWithComfortIndex($cityCode);
            
            if ($weatherData) {
                // Add human-readable explanation of penalties
                $penaltyBreakdown = $weatherData['comfort_index']['penalties'] ?? [];
                $explanation = $this->generateHumanReadableExplanation($penaltyBreakdown, $weatherData);
                
                return response()->json([
                    'success' => true,
                    'data' => $weatherData,
                    'explanation' => $explanation
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Weather data not found for city code: ' . $cityCode
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate human-readable explanation of penalties
     */
    private function generateHumanReadableExplanation($penalties, $weatherData)
    {
        $temp = $weatherData['temperature'];
        $humidity = $weatherData['humidity'];
        $wind = $weatherData['wind_speed'];
        $condition = $weatherData['weather_condition'];
        $score = $weatherData['comfort_index']['score'];
        
        $explanation = "The comfort index for {$weatherData['city_name']} is {$score}/100. ";
        
        // Temperature explanation
        if ($temp >= 18 && $temp <= 24) {
            $explanation .= "Temperature at {$temp}°C is ideal. ";
        } elseif ($temp < 5) {
            $explanation .= "Temperature at {$temp}°C is dangerously cold (EXTREME PENALTY)! ";
        } elseif ($temp > 35) {
            $explanation .= "Temperature at {$temp}°C is dangerously hot (EXTREME PENALTY)! ";
        } elseif ($temp < 10) {
            $explanation .= "Temperature at {$temp}°C is cold. ";
        } elseif ($temp > 30) {
            $explanation .= "Temperature at {$temp}°C is hot. ";
        } else {
            $explanation .= "Temperature at {$temp}°C is acceptable. ";
        }
        
        // Humidity explanation
        if ($humidity >= 40 && $humidity <= 60) {
            $explanation .= "Humidity at {$humidity}% is ideal. ";
        } elseif ($humidity > 80) {
            $explanation .= "Humidity at {$humidity}% is extremely humid (EXTREME PENALTY)! ";
        } elseif ($humidity < 20) {
            $explanation .= "Humidity at {$humidity}% is extremely dry (EXTREME PENALTY)! ";
        } elseif ($humidity > 70) {
            $explanation .= "Humidity at {$humidity}% is high. ";
        } elseif ($humidity < 30) {
            $explanation .= "Humidity at {$humidity}% is low. ";
        } else {
            $explanation .= "Humidity at {$humidity}% is acceptable. ";
        }
        
        // Wind explanation
        if ($wind >= 5 && $wind <= 15) {
            $explanation .= "Wind at {$wind} km/h is ideal. ";
        } elseif ($wind > 40) {
            $explanation .= "Wind at {$wind} km/h is very strong! ";
        } elseif ($wind > 25) {
            $explanation .= "Wind at {$wind} km/h is strong. ";
        } elseif ($wind < 3) {
            $explanation .= "Wind at {$wind} km/h is calm. ";
        } else {
            $explanation .= "Wind at {$wind} km/h is acceptable. ";
        }
        
        // Weather condition explanation
        if (strpos(strtolower($condition), 'clear') !== false || strpos(strtolower($condition), 'sunny') !== false) {
            $explanation .= "Skies are clear - perfect! ";
        } elseif (strpos(strtolower($condition), 'rain') !== false || strpos(strtolower($condition), 'drizzle') !== false) {
            $explanation .= "It's raining - this reduces comfort. ";
        } elseif (strpos(strtolower($condition), 'cloud') !== false) {
            $explanation .= "It's cloudy - moderate impact. ";
        } elseif (strpos(strtolower($condition), 'thunder') !== false) {
            $explanation .= "Thunderstorm! - significant comfort reduction. ";
        } else {
            $explanation .= "Weather conditions are mixed. ";
        }
        
        // Final summary
        if ($score >= 85) {
            $explanation .= "Overall, this is EXCELLENT weather for any outdoor activity!";
        } elseif ($score >= 70) {
            $explanation .= "Overall, this is GOOD weather for most activities.";
        } elseif ($score >= 55) {
            $explanation .= "Overall, this is MODERATE weather with some discomfort.";
        } elseif ($score >= 40) {
            $explanation .= "Overall, this is FAIR weather - you may need to adjust your plans.";
        } else {
            $explanation .= "Overall, this is POOR weather - consider staying indoors.";
        }
        
        return $explanation;
    }

    public function getCachedWeather()
    {
        try {
            $weatherData = cache()->remember('all_cities_weather', 3600, function () {
                return $this->weatherService->fetchAllCitiesWeather();
            });

            return response()->json([
                'success' => true,
                'data' => $weatherData
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
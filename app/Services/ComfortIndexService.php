<?php

namespace App\Services;

class ComfortIndexService
{
    /**
     * Calculate the unique Urban Comfort Balance Index
     * Scale: 0-100 (Higher = More Comfortable)
     */
    public function calculateComfortIndex($temperature, $humidity, $windSpeed, $weatherCondition)
    {
        // 1. Temperature Factor (Ideal: 21°C)
        $tempFactor = max(0, 1 - (abs($temperature - 21) / 50));
        
        // 2. Humidity Factor (Ideal: 50%)
        $humidityFactor = max(0, 1 - (abs($humidity - 50) / 50));
        
        // 3. Wind Factor (Ideal: 10 km/h)
        $windFactor = max(0, min(1, 1 - (abs($windSpeed - 10) / 50)));
        
        // 4. Weather Condition Factor
        $conditionFactor = $this->getWeatherConditionWeight($weatherCondition);
        $weatherConditionFactor = $conditionFactor['weight'] * $conditionFactor['clarity'];
        
        // 5. Weighted Average Score (0-1 scale)
        $baseScore = (
            ($tempFactor * 0.35) +
            ($humidityFactor * 0.25) +
            ($windFactor * 0.20) +
            ($weatherConditionFactor * 0.20)
        );
        
        // 6. Calculate Extremes Penalty
        $extremesPenalty = $this->calculateExtremesPenalty($temperature, $humidity);
        
        // 7. Apply penalty and scale to 0-100
        $finalScore = max(0, min(100, ($baseScore - $extremesPenalty) * 100));
        
        return [
            'score' => round($finalScore, 1),
            'factors' => [
                'temperature_factor' => round($tempFactor * 100, 1),
                'humidity_factor' => round($humidityFactor * 100, 1),
                'wind_factor' => round($windFactor * 100, 1),
                'weather_condition_factor' => round($weatherConditionFactor * 100, 1),
                'extremes_penalty' => round($extremesPenalty * 100, 1),
            ],
            'interpretation' => $this->getInterpretation($finalScore)
        ];
    }
    
    /**
     * Get weather condition weight based on OpenWeatherMap status
     */
    private function getWeatherConditionWeight($condition)
    {
        $condition = strtolower($condition);
        
        $weights = [
            'clear' => ['weight' => 1.0, 'clarity' => 1.0],
            'sunny' => ['weight' => 1.0, 'clarity' => 1.0],
            'few clouds' => ['weight' => 0.95, 'clarity' => 0.95],
            'scattered clouds' => ['weight' => 0.9, 'clarity' => 0.9],
            'broken clouds' => ['weight' => 0.8, 'clarity' => 0.8],
            'overcast clouds' => ['weight' => 0.7, 'clarity' => 0.7],
            'clouds' => ['weight' => 0.75, 'clarity' => 0.75],
            'light rain' => ['weight' => 0.6, 'clarity' => 0.7],
            'drizzle' => ['weight' => 0.6, 'clarity' => 0.7],
            'moderate rain' => ['weight' => 0.5, 'clarity' => 0.6],
            'rain' => ['weight' => 0.4, 'clarity' => 0.5],
            'heavy rain' => ['weight' => 0.3, 'clarity' => 0.4],
            'thunderstorm' => ['weight' => 0.2, 'clarity' => 0.3],
            'mist' => ['weight' => 0.5, 'clarity' => 0.4],
            'fog' => ['weight' => 0.4, 'clarity' => 0.3],
            'haze' => ['weight' => 0.5, 'clarity' => 0.5],
            'smoke' => ['weight' => 0.3, 'clarity' => 0.3],
            'snow' => ['weight' => 0.4, 'clarity' => 0.5],
            'extreme' => ['weight' => 0.1, 'clarity' => 0.1],
        ];
        
        // Find matching condition
        foreach ($weights as $key => $value) {
            if (strpos($condition, $key) !== false) {
                return $value;
            }
        }
        
        // Default for unknown conditions
        return ['weight' => 0.5, 'clarity' => 0.5];
    }
    
    /**
     * Calculate penalties for extreme conditions
     */
    private function calculateExtremesPenalty($temperature, $humidity)
    {
        $penalty = 0;
        
        // Cold Spike Penalty (below 5°C)
        if ($temperature < 5) {
            $penalty += (5 - $temperature) / 10;
        }
        
        // Heat Spike Penalty (above 35°C)
        if ($temperature > 35) {
            $penalty += ($temperature - 35) / 15;
        }
        
        // Humidity Spike Penalty (above 80%)
        if ($humidity > 80) {
            $penalty += ($humidity - 80) / 20;
        }
        
        // Humidity Too Low (below 20%)
        if ($humidity < 20) {
            $penalty += (20 - $humidity) / 20;
        }
        
        // Cap the penalty
        return min(0.5, $penalty * 0.25);
    }
    
    /**
     * Get interpretation of comfort score
     */
    private function getInterpretation($score)
    {
        if ($score >= 85) {
            return 'Excellent - Perfect weather conditions for outdoor activities';
        } elseif ($score >= 70) {
            return 'Good - Comfortable conditions, ideal for most activities';
        } elseif ($score >= 55) {
            return 'Moderate - Acceptable conditions with minor discomforts';
        } elseif ($score >= 40) {
            return 'Fair - Noticeable discomfort, may need adjustments';
        } elseif ($score >= 25) {
            return 'Poor - Significant discomfort, outdoor activities not recommended';
        } else {
            return 'Very Poor - Extreme conditions, avoid prolonged exposure';
        }
    }
}
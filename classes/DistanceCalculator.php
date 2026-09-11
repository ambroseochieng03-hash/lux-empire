<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/app.php';

/**
 * LUX EMPIRE
 * Server-side distance + price calculation via Google's Distance
 * Matrix API. Deliberately never trusts a client-submitted price —
 * every price that actually gets stored is computed here, from
 * coordinates the server itself validates.
 */
final class DistanceCalculator
{
    public static function calculate(float $originLat, float $originLng, float $destLat, float $destLng): ?array
    {
        if (empty(GOOGLE_SERVER_API_KEY)) {
            error_log('LUX EMPIRE DistanceCalculator: GOOGLE_SERVER_API_KEY is not configured.');
            return null;
        }

        $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?' . http_build_query([
            'origins' => $originLat . ',' . $originLng,
            'destinations' => $destLat . ',' . $destLng,
            'units' => 'metric',
            'key' => GOOGLE_SERVER_API_KEY,
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
        ]);

        $response = curl_exec($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error || !$response) {
            error_log('LUX EMPIRE DistanceCalculator: request failed: ' . $error);
            return null;
        }

        $data = json_decode($response, true);
        $element = $data['rows'][0]['elements'][0] ?? null;

        if (!$element || ($element['status'] ?? '') !== 'OK') {
            error_log('LUX EMPIRE DistanceCalculator: no route found: ' . json_encode($data));
            return null;
        }

        $distanceKm = round($element['distance']['value'] / 1000, 2);
        $durationText = $element['duration']['text'] ?? null;

        $price = max(
            TRUCK_MINIMUM_FARE,
            TRUCK_BASE_FARE + ($distanceKm * TRUCK_RATE_PER_KM)
        );

        return [
            'distance_km' => $distanceKm,
            'duration_text' => $durationText,
            'price' => round($price, 2),
        ];
    }
}

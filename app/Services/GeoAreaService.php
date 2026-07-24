<?php

namespace App\Services;

class GeoAreaService
{
    private const EARTH_RADIUS_M = 6371000.0;

    /**
     * Compute spherical polygon area in m².
     * Formula: Area = (R²/2) * abs(Σ (λ_{i+1} - λ_{i-1}) * sin(φ_i))
     *
     * @param  array<int, array{lat?: float, lng?: float, latitude?: float, longitude?: float}|array{0: float, 1: float}>  $latLngPoints
     */
    public function sphericalPolygonAreaM2(array $latLngPoints): float
    {
        $n = count($latLngPoints);
        if ($n < 3) {
            return 0.0;
        }

        // Drop duplicate closing vertex if present
        $first = $this->normalizeLatLng($latLngPoints[0]);
        $last = $this->normalizeLatLng($latLngPoints[$n - 1]);
        if (abs($first['lat'] - $last['lat']) < 1e-12 && abs($first['lng'] - $last['lng']) < 1e-12) {
            array_pop($latLngPoints);
            $n = count($latLngPoints);
            if ($n < 3) {
                return 0.0;
            }
        }

        $sum = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $prev = $this->normalizeLatLng($latLngPoints[($i - 1 + $n) % $n]);
            $curr = $this->normalizeLatLng($latLngPoints[$i]);
            $next = $this->normalizeLatLng($latLngPoints[($i + 1) % $n]);

            $phi = deg2rad($curr['lat']);
            $lambdaPrev = deg2rad($prev['lng']);
            $lambdaNext = deg2rad($next['lng']);

            $sum += ($lambdaNext - $lambdaPrev) * sin($phi);
        }

        return (self::EARTH_RADIUS_M * self::EARTH_RADIUS_M / 2.0) * abs($sum);
    }

    /**
     * @param  array{type?: string, coordinates?: mixed}  $geojson
     */
    public function areaFromGeoJsonPolygon(array $geojson): float
    {
        $type = $geojson['type'] ?? null;
        if ($type !== 'Polygon') {
            throw new \InvalidArgumentException('GeoJSON must be a Polygon.');
        }

        $coordinates = $geojson['coordinates'] ?? null;
        if (! is_array($coordinates) || empty($coordinates[0]) || ! is_array($coordinates[0])) {
            throw new \InvalidArgumentException('GeoJSON Polygon is missing coordinates.');
        }

        $ring = $coordinates[0];
        $latLngPoints = [];
        foreach ($ring as $pair) {
            if (! is_array($pair) || count($pair) < 2) {
                continue;
            }
            // GeoJSON: [lng, lat]
            $latLngPoints[] = [
                'lat' => (float) $pair[1],
                'lng' => (float) $pair[0],
            ];
        }

        return $this->sphericalPolygonAreaM2($latLngPoints);
    }

    public function validateClientArea(float $clientArea, float $serverArea, float $tolerance = 0.1): bool
    {
        if ($serverArea <= 0.0) {
            return abs($clientArea) <= 1e-6;
        }

        $relativeDiff = abs($clientArea - $serverArea) / $serverArea;

        return $relativeDiff <= $tolerance;
    }

    /**
     * @param  array{lat?: float, lng?: float, latitude?: float, longitude?: float}|array{0: float, 1: float}  $point
     * @return array{lat: float, lng: float}
     */
    private function normalizeLatLng(array $point): array
    {
        if (array_key_exists('lat', $point) || array_key_exists('lng', $point)) {
            return [
                'lat' => (float) ($point['lat'] ?? 0),
                'lng' => (float) ($point['lng'] ?? 0),
            ];
        }

        if (array_key_exists('latitude', $point) || array_key_exists('longitude', $point)) {
            return [
                'lat' => (float) ($point['latitude'] ?? 0),
                'lng' => (float) ($point['longitude'] ?? 0),
            ];
        }

        // Assume [lat, lng] when numeric keys
        return [
            'lat' => (float) ($point[0] ?? 0),
            'lng' => (float) ($point[1] ?? 0),
        ];
    }
}

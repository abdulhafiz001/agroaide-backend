<?php

namespace Tests\Unit;

use App\Services\DiseaseOutbreakService;
use PHPUnit\Framework\TestCase;

class DiseaseOutbreakDistanceTest extends TestCase
{
    public function test_same_point_is_zero_km(): void
    {
        $km = DiseaseOutbreakService::distanceKm(9.0579, 7.4951, 9.0579, 7.4951);

        $this->assertEqualsWithDelta(0.0, $km, 0.001);
    }

    public function test_nearby_points_inside_five_km_radius(): void
    {
        // ~2.2 km north of Abuja reference point
        $km = DiseaseOutbreakService::distanceKm(9.0579, 7.4951, 9.0779, 7.4951);

        $this->assertGreaterThan(1.5, $km);
        $this->assertLessThan(5.0, $km);
    }

    public function test_distant_city_outside_five_km_radius(): void
    {
        // Lagos vs Abuja — hundreds of km
        $km = DiseaseOutbreakService::distanceKm(9.0579, 7.4951, 6.5244, 3.3792);

        $this->assertGreaterThan(100.0, $km);
    }
}

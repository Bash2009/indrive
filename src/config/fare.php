<?php

function calculate_fare($distance_km, $vehicle_type) {
    $rates = [
        'economy' => ['base' => 2.50, 'per_km' => 0.80],
        'comfort' => ['base' => 4.00, 'per_km' => 1.20],
        'premium' => ['base' => 6.00, 'per_km' => 1.80],
    ];

    if (!isset($rates[$vehicle_type])) {
        $vehicle_type = 'economy';
    }

    $rate = $rates[$vehicle_type];
    $fare = $rate['base'] + ($distance_km * $rate['per_km']);
    return round($fare, 2);
}

function haversine_km($lat1, $lng1, $lat2, $lng2) {
    $earth_radius = 6371;
    $d_lat = deg2rad($lat2 - $lat1);
    $d_lng = deg2rad($lng2 - $lng1);
    $a = sin($d_lat / 2) * sin($d_lat / 2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($d_lng / 2) * sin($d_lng / 2);
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return round($earth_radius * $c, 2);
}

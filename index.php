<?php

require_once __DIR__ . '/vendor/autoload.php';

use Yama\CuacaBmkg\CuacaBmkg;


$cuaca = new CuacaBmkg();

$provinsi = $cuaca->getProvinsi();

// echo json_encode($provinsi);

$weather = $cuaca->getWeather('DKIJakarta');

echo json_encode($weather);
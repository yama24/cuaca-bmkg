<?php

require_once __DIR__ . '/vendor/autoload.php';

use Yama\CuacaBmkg\CuacaBmkg;


$cuaca = new CuacaBmkg();

$provinsi = $cuaca->get('provinsi');

// echo json_encode($provinsi);die;

$cuaca->weather('DKIJakarta');

// $weather = $cuaca->get('area');
$weather = $cuaca->getAreaByName('%barat%');

var_dump($weather);
<?php

require_once __DIR__ . '/vendor/autoload.php';

use CloudRail\Service\Foursquare;
use CloudRail\Service\GooglePlaces;
use CloudRail\Settings;

Settings::$licenseKey = "[CLOUDRAIL_KEY]";

/**
 * @var \CloudRail\Interfaces\PointsOfInterest
 */
$service = null;

/**
 * @var string
 */
$serviceName = "Foursquare"; //TODO:Just change the interface name :)

switch ($serviceName){
    case "Foursquare":
        $service = new Foursquare( "[CLIENT_ID]","[CLIENT_SECRET]");
        break;
    case "Yelp":
        $service = new \CloudRail\Service\Yelp( "[API_KEY]");
        break;
    case "GooglePlaces":
        $service = new GooglePlaces( "[API_KEY]");
        break;
}

$retrievedPOIs = $service->getNearbyPOIs( -15.7662,-47.8829,3000,"cafe",[]);

 var_dump($retrievedPOIs);

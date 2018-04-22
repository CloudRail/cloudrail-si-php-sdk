<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 15/12/17
 * Time: 10:37
 */

namespace CloudRail\Interfaces;

interface PointsOfInterest {
    /**
     * @param float $latitude The latitude of the target location.
     * @param float $longtitude The longitude of the target location.
     * @param float $radius The search radius in metres.
     * @param string $searchTerm Optional search term that has to be matched.
     * @param string[] $categories Optional  array of strings matching the categories to search.
     * @return POI[] of POIs that are close to the passed location and filters them by certain criteria.
     */
    public function getNearbyPOIs(float $latitude, float $longtitude, int $radius, string $searchTerm, array $categories):array;

}
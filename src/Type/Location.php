<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 15/12/17
 * Time: 15:26
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class Location extends SandboxObject{
    /**
     * @var float The float value of latitude
     */
    public $longitude;

    /**
     * @var float The float value of longitude
     */
    public $latitude;

    public function __construct(float ...$addressValues) {
        if (count($addressValues) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($addressValues) == count(get_object_vars($this))) {
            $this->longitude = $addressValues[0];
            $this->latitude = $addressValues[1];
        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * @param float $longitude
     * @param float $latitude
     * @return Location
     */
    public static function new(float $longitude, float $latitude) {
        $newObject = new Location();

        $newObject->longitude = $longitude;
        $newObject->latitude = $latitude;
        return $newObject;
    }

}
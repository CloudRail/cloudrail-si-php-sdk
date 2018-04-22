<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 15/12/17
 * Time: 10:54
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class POI extends  SandboxObject {
    /**
     * @var array[string]
     */
    public $categories;

    /**
     * @var string
     */
    public $imageURL;

    /**
     * @var Location
     */
    public $location;

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $phone;


    /**
     * POI constructor.
     * @param array ...$addressValues
     * @throws InternalError
     */
    public function __construct( ...$addressValues) {
        if (count($addressValues) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($addressValues) == count(get_object_vars($this))) {
            $this->categories = $addressValues[0];
            $this->imageURL = $addressValues[1];
            $this->location = $addressValues[2];
            $this->name = $addressValues[3];
            $this->phone = $addressValues[4];

        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * @param array[string] $categories
     * @param string $imageURL
     * @param Location $location
     * @param string $name
     * @param string $phone
     * @return POI
     */
    public static function new( array $categories, string $imageURL, Location $location, string $name, string $phone) {
        $newObject = new POI();
        $newObject->categories = $categories;
        $newObject->imageURL = $imageURL;
        $newObject->location = $location;
        $newObject->name = $name;
        $newObject->phone = $phone;
    }

}
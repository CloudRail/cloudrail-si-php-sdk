<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 08/01/18
 * Time: 15:30
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class Address extends  SandboxObject{
    /**
     * @var string
     */
    public $country;

    /**
     * @var string
     */
    public $city;

    /**
     * @var string
     */
    public $state;

    /**
     * @var string
     */
    public $line1;

    /**
     * @var string
     */
    public $line2;

    /**
     * @var string
     */
    public $postalCode;

    /**
     * Address constructor.
     * @param string[] ...$addressValues
     * @throws InternalError
     */
    public function __construct(string ...$addressValues) {
        if (count($addressValues) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($addressValues) == count(get_object_vars($this))) {
            $this->country = $addressValues[0];
            $this->city = $addressValues[1];
            $this->state = $addressValues[2];
            $this->line1 = $addressValues[3];
            $this->line2 = $addressValues[4];
            $this->postalCode = $addressValues[5];
        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * Address constructor.
     *
     * @param string $country
     * @param string $city
     * @param string $state
     * @param string $line1
     * @param string $line2
     * @param string $postalCode
     * @return Address
     */
    public static function new(string $country, string $city, string $state, string $line1, string $line2, string $postalCode){
        $newObject = new Address();

        $newObject->country = $country;
        $newObject->city = $city;
        $newObject->state = $state;
        $newObject->line1 = $line1;
        $newObject->line2 = $line2;
        $newObject->postalCode = $postalCode;

        return $newObject;
    }


}

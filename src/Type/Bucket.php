<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 13/02/18
 * Time: 04:41
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class Bucket extends SandboxObject {

    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $identifier;


    /**
     * Bucket constructor.
     * @param string[] ...$values
     * @throws InternalError
     */
    public function __construct(string ...$values) {
        if (count($values) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($values) == count(get_object_vars($this))) {
            $this->name = $values[0];
            $this->identifier = $values[1];

        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }


    /**
     * @param string $name
     * @param string $identifier
     * @return Bucket
     */
    public static function new(string $name, string $identifier){
        $newObject = new Bucket();
        $newObject->identifier = $identifier;
        $newObject->name = $name;
        return $newObject;
    }

}
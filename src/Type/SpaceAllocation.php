<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 22/01/18
 * Time: 10:43
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class SpaceAllocation extends SandboxObject {

    /**
     * @var integer
     */
    public $used;

    /**
     * @var integer
     */
    public $total;

    /**
     * SpaceAllocation constructor.
     * @param array ...$addressValues
     * @throws InternalError
     */
    public function __construct( ...$addressValues) {
        if (count($addressValues) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($addressValues) == count(get_object_vars($this))) {
            $this->used = $addressValues[0];
            $this->total = $addressValues[1];
        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * @param $height
     * @param $width
     * @return SpaceAllocation
     */
    public static function new( integer $used, integer $total) {
        $newObject = new SpaceAllocation();
        $newObject->used = $used;
        $newObject->total = $total;
        return $newObject;
    }
}
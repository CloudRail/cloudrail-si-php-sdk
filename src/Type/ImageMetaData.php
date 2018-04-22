<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 22/01/18
 * Time: 10:48
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class ImageMetaData extends SandboxObject {

    public $height;
    public $width;

    /**
     * ImageMetaData constructor.
     * @param array ...$addressValues
     * @throws InternalError
     */
    public function __construct( ...$addressValues) {
        if (count($addressValues) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($addressValues) == count(get_object_vars($this))) {
            $this->height = $addressValues[0];
            $this->width = $addressValues[1];
        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * @param $height
     * @param $width
     * @return ImageMetaData
     */
    public static function new( $height,  $width) {
        $newObject = new ImageMetaData();
        $newObject->height = $height;
        $newObject->width = $width;
        return $newObject;
    }

}
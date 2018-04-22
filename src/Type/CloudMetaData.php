<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 13/02/18
 * Time: 03:58
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class CloudMetaData extends SandboxObject {
    /**
     * @var string
     */
    public $path;

    /**
     * @var string
     */
    public $name;

    /**
     * @var integer
     */
    public $size;

    /**
     * @var boolean
     */
    private $folder;

    /**
     * @var integer
     */
    public $modifiedAt;

    /**
     * @var ImageMetaData
     */
    public $imageMetaData;


    /**
     * CloudMetadata constructor.
     * @param array ...$values
     * @throws InternalError
     */
    public function __construct( ...$values) {
        if (count($values) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($values) == count(get_object_vars($this))) {
            $this->path = $values[0];
            $this->name = $values[1];
            $this->size = $values[2];
            $this->folder = $values[3];
            $this->modifiedAt = $values[4];
            $this->imageMetaData = $values[5];
        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }


    /**
     * @param string $path
     * @param string $name
     * @param int $size
     * @param bool $folder
     * @param int $modifiedAt
     * @param ImageMetaData $imageMetaData
     * @return CloudMetaData
     */
    public static function new(string $path,
                               string $name,
                               integer $size,
                               bool $folder,
                               int $modifiedAt,
                               ImageMetaData $imageMetaData){
        $newObject = new CloudMetaData();

        $newObject->path = $path;
        $newObject->name = $name;
        $newObject->size = $size;
        $newObject->folder = $folder;
        $newObject->modifiedAt = $modifiedAt;
        $newObject->imageMetaData = $imageMetaData;

        return $newObject;
    }


    public function setFolder($value){
        $this->folder = !!$value;
    }

    public function getFolder():bool {
        return $this->folder;
    }

    public function getContentModifiedAt():bool {
        return $this->modifiedAt;
    }

    public function setContentModifiedAt( $timestamp):bool {
//        Helper::assert(Helper::isInteger($timestamp));
        return $this->modifiedAt = $timestamp;
    }
}
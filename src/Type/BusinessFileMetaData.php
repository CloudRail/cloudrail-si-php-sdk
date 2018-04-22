<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 13/02/18
 * Time: 05:00
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class BusinessFileMetaData extends SandboxObject {

    /**
     * @var string
     */
    public $fileName;

    /**
     * @var string
     */
    public $fileID;

    /**
     * @var integer
     */
    public $size;

    /**
     * @var integer
     */
    public $lastModified;


    /**
     * BusinessFileMetaData constructor.
     * @param array ...$values
     * @throws InternalError
     */
    public function __construct( ...$values) {
        if (count($values) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($values) == count(get_object_vars($this))) {
            $this->fileName = $values[0];
            $this->fileID = $values[1];
            $this->size = $values[2];
            $this->lastModified = $values[3];
        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }


    /**
     * @param string $fileName
     * @param string $fileID
     * @param int $size
     * @param int $lastModified
     * @return BusinessFileMetaData
     */
    public static function new(string $fileName, string $fileID, integer $size, integer $lastModified){
        $newObject = new BusinessFileMetaData();
        $newObject->fileName = $fileName;
        $newObject->fileID = $fileID;
        $newObject->size = $size;
        $newObject->lastModified = $lastModified;
        return $newObject;
    }


}
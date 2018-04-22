<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 22/01/18
 * Time: 10:45
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class MessagingAttachment extends SandboxObject {

    /**
     * @var string
     */
    public $id;

    /**
     * @var string
     */
    public $contentType;

    /**
     * @var string
     */
    public $mimeType;

    /**
     * @var string
     */
    public $caption;

    /**
     * @var resource
     */
    public $stream;

    /**
     * MessagingAttachment constructor.
     * @param array ...$addressValues
     * @throws InternalError
     */
    public function __construct( ...$addressValues) {
        if (count($addressValues) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($addressValues) == count(get_object_vars($this))) {
            $this->id = $addressValues[0];
            $this->contentType = $addressValues[1];
            $this->mimeType = $addressValues[2];
            $this->caption = $addressValues[3];
            $this->stream = $addressValues[4];

        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * @param string $id
     * @param string $contentType
     * @param string $mimeType
     * @param string $caption
     * @param resource $stream
     * @return MessagingAttachment
     */
    public static function new( string $id, string $contentType, string $mimeType, string $caption, resource $stream) {
        $newObject = new MessagingAttachment();
        $newObject->id = $id;
        $newObject->contentType = $contentType;
        $newObject->mimeType = $mimeType;
        $newObject->caption = $caption;
        $newObject->stream = $stream;
        return $newObject;
    }
}
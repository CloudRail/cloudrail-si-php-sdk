<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 22/01/18
 * Time: 10:47
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class MessageButton extends SandboxObject {

    /**
     * @var string
     */
    public $text;

    /**
     * @var string
     */
    public $type;

    /**
     * @var string
     */
    public $payload;

    /**
     * @var string
     */
    public $url;

    /**
     * MessageButton constructor.
     * @param array ...$addressValues
     * @throws InternalError
     */
    public function __construct( ...$addressValues) {
        if (count($addressValues) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($addressValues) == count(get_object_vars($this))) {
            $this->text = $addressValues[0];
            $this->type = $addressValues[1];
            $this->payload = $addressValues[2];
            $this->url = $addressValues[3];
        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * @param string $text
     * @param string $type
     * @param string $payload
     * @param string $url
     * @return MessageButton
     */
    public static function new( string $text, string $type, string $payload, string $url) {
        $newObject = new MessageButton();
        $newObject->text = $text;
        $newObject->type =  $type;
        $newObject->payload = $payload;
        $newObject->url = $url;
        return $newObject;
    }
}
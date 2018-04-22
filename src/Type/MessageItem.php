<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 22/01/18
 * Time: 10:45
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class MessageItem extends SandboxObject {
    /**
     * @var string
     */
    public $title;

    /**
     * @var string
     */
    public $subTitle;

    /**
     * @var string
     */
    public $mediaUrl;

    /**
     * @var array[MessageButton]
     */
    public $buttons;

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
            $this->title = $addressValues[0];
            $this->subTitle = $addressValues[1];
            $this->mediaUrl = $addressValues[2];
            $this->buttons = $addressValues[3];

        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * @param string $title
     * @param string $subTitle
     * @param string $mediaUrl
     * @param array $buttons
     * @return MessageItem
     */
    public static function new( string $title, string $subTitle, string $mediaUrl, array $buttons) {
        $newObject = new MessageItem();
        $newObject->title = $title;
        $newObject->subTitle = $subTitle;
        $newObject->mediaUrl = $mediaUrl;
        $newObject->buttons = $buttons;
        return $newObject;
    }

}
<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 22/01/18
 * Time: 10:47
 */


namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class Message extends SandboxObject {
    /**
     * @var string
     */
    public $messageId;

    /**
     * @var string
     */
    public $senderId;

    /**
     * @var string
     */
    public $chatId;

    /**
     * @var string
     */
    public $replyTo;

    /**
     * @var string
     */
    public $editOf;

    /**
     * @var int
     */
    public $sendAt;

    /**
     * @var string
     */
    public $messageText;

    /**
     * @var Location
     */
    public $location;

    /**
     * @var array[MessagingAttachment]
     */
    public $attachments;

    /**
     * Message constructor.
     * @param array ...$addressValues
     * @throws InternalError
     */
    public function __construct( ...$addressValues) {
        if (count($addressValues) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($addressValues) == count(get_object_vars($this))) {
            $this->messageId = $addressValues[0];
            $this->senderId = $addressValues[1];
            $this->chatId = $addressValues[2];
            $this->replyTo = $addressValues[3];
            $this->editOf = $addressValues[4];
            $this->sendAt = $addressValues[5];
            $this->messageText = $addressValues[6];
            $this->location = $addressValues[7];
            $this->attachments = $addressValues[8];
        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * @param string $messageId
     * @param string $senderId
     * @param string $chatId
     * @param string $replyTo
     * @param string $editOf
     * @param int $sendAt
     * @param string $messageText
     * @param Location $location
     * @param array $attachments
     * @return Message
     */
    public static function new(string $messageId, string $senderId,
                               string $chatId, string $replyTo, string $editOf,
                               int $sendAt, string $messageText,
                               Location $location, array $attachments){
        $newObject = new Message();
        $newObject->messageId = $messageId;
        $newObject->senderId = $senderId;
        $newObject->chatId = $chatId;
        $newObject->replyTo = $replyTo;
        $newObject->editOf = $editOf;
        $newObject->sendAt = $sendAt;
        $newObject->messageText = $messageText;
        $newObject->location = $location;
        $newObject->attachments = $attachments;
        return $newObject;
    }
}
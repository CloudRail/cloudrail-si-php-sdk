<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 22/01/18
 * Time: 10:41
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

/**
 * Class VideoMetaData
 */
class VideoMetaData extends SandboxObject {

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $description;

    /**
     * @var integer
     */
    private $publishedAt;

    /**
     * @var string
     */
    private $channelId;

    /**
     * @var integer
     */
    private $duration;

    /**
     * @var string
     */
    private $thumbnailUrl;

    /**
     * @var string
     */
    private $embedHtml;

    /**
     * @var integer
     */
    private $viewCount;

    /**
     * @var integer
     */
    private $likeCount;

    /**
     * @var integer
     */
    private $dislikeCount;


    /**
     * ChannelMetaData constructor.
     * @param string[] ...$values
     * @throws InternalError
     */
    public function __construct( ...$values) {
        if (count($values) == 0 ){
            return $this;
        }
        //if there are parameters, they need to fill all the properties, they also need to be in order
        if (count($values) == count(get_object_vars($this))) {
            $this->id = $values[0];
            $this->title = $values[1];
            $this->description = $values[2];
            $this->publishedAt = $values[3];
            $this->channelId = $values[4];
            $this->duration = $values[5];
            $this->thumbnailUrl = $values[6];
            $this->embedHtml = $values[7];
            $this->viewCount = $values[8];
            $this->likeCount = $values[9];
            $this->dislikeCount = $values[10];


        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }

    /**
     * VideoMetaData constructor.
     * @param string $id
     * @param string $title
     * @param string $description
     * @param int $publishedAt
     * @param string $channelId
     * @param int $duration
     * @param string $thumbnailUrl
     * @param string $embedHtml
     * @param int $viewCount
     * @param int $likeCount
     * @param int $dislikeCount
     * @return VideoMetaData
     */
    public static function new($id, $title, $description, $publishedAt, $channelId, $duration, $thumbnailUrl, $embedHtml, $viewCount, $likeCount, $dislikeCount)
    {
        $newObject = new VideoMetaData();

        $newObject->id = $id;
        $newObject->title = $title;
        $newObject->description = $description;
        $newObject->publishedAt = $publishedAt;
        $newObject->channelId = $channelId;
        $newObject->duration = $duration;
        $newObject->thumbnailUrl = $thumbnailUrl;
        $newObject->embedHtml = $embedHtml;
        $newObject->viewCount = $viewCount;
        $newObject->likeCount = $likeCount;
        $newObject->dislikeCount = $dislikeCount;

        return $newObject;
    }
}

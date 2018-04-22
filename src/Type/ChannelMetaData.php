<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 13/02/18
 * Time: 05:10
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

class ChannelMetaData extends SandboxObject {

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $name;

    /**
     * @var integer
     */
    private $followers;

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $logoUrl;

    /**
     * @var string
     */
    private $bannerUrl;

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
            $this->name = $values[1];
            $this->followers = $values[2];
            $this->url = $values[3];
            $this->logoUrl = $values[4];
            $this->bannerUrl = $values[5];

        } else {
            throw new InternalError("Constructor needs all arguments to be initialized in class " . get_class($this));
        }
        return $this;
    }


    public function getId():string {
        return $this->id;
    }

    public function getName():string {
        return $this->name;
    }

    public function getFollowers():number {
        return $this->followers;
    }

    public function getUrl():string {
        return $this->url;
    }

    public function getLogoUrl():string {
        return $this->logoUrl;
    }

    public function getBannerUrl():string {
        return $this->bannerUrl;
    }

    /**
     * @param string $id
     * @param string $name
     * @param int $followers
     * @param string $url
     * @param string $logoUrl
     * @param string $bannerUrl
     * @return ChannelMetaData
     */
    public static function new(string $id,
                               string $name,
                               integer $followers,
                               string $url,
                               string $logoUrl,
                               string $bannerUrl){

        $newObject = new ChannelMetaData();
        $newObject->id = $id;
        $newObject->name = $name;
        $newObject->followers = $followers;
        $newObject->url = $url;
        $newObject->logoUrl = $logoUrl;
        $newObject->bannerUrl = $bannerUrl;
        return $newObject;
    }
}
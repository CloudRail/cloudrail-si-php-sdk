<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 19/11/17
 * Time: 21:57
 */

namespace CloudRail;

class Settings{
    /**
     * @var string
     */
    public static $licenseKey;

    public static $block = false;


    public static function setKey(string $key){
        Settings::$licenseKey = $key;
    }

}
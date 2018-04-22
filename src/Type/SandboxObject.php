<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 15/12/17
 * Time: 10:52
 */

namespace CloudRail\Type;

use CloudRail\Error\InternalError;

require_once __DIR__ . "/../ServiceCode/Helper.php";

class SandboxObject implements Comparable {

    public function set(string $key, $value) {

        $key = lcfirst($key);//First letter always lower case

        $setterName = "set" . ucfirst($key); //setter name

        //checking if method exists
        $reflection = new \ReflectionClass(get_class($this));
        $methodExists = $reflection->hasMethod($setterName);
        if ($methodExists) {
            $isPublic = $reflection->getMethod($setterName)->isPublic(); // if method is public
            if($isPublic) { $this->$setterName($value); }
        } else if($reflection->hasProperty($key) &&
            $reflection->getProperty($key)->isPublic()){ // check if property exists and is public
            $this->$key = $value;
        } else {
            throw new InternalError("Property or setter not found");
        }
    }

    public function get(string $key) {

        $key = lcfirst($key);//First letter always lower case

        $getterName = "get" . ucfirst($key); //getter name

        $reflection = new \ReflectionClass(get_class($this));
        $methodExists = $reflection->hasMethod($getterName);
        if ($methodExists) {
            $isPublic = $reflection->getMethod($getterName)->isPublic(); // if method is public
            if($isPublic) {
                return $this->$getterName();
            }
        } else if($reflection->hasProperty($key) &&
            $reflection->getProperty($key)->isPublic()){ // check if property exists and is public
            return $this->$key;
        } else {
            throw new InternalError("Property or getter not found");
        }
        return null;
    }


    function compareTo($object): int {
        return Comparator::objectsAreIdentical($this, $object);
    }
}
<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 12/03/18
 * Time: 04:16
 */

namespace CloudRail\Interfaces;

interface Persistable {
    /**
     * A method to retrieve the data from a service that is intended for persistent storage
     * @return string The data of the service that should be stored persistently, e.g. access credentials
     */
    public function saveAsString():string;

    /**
     * Loads/restores data saved by {@link #saveAsString() saveAsString} into the service
     * @param string $savedState The persistent data that was stored
     */
    public function loadAsString(string $savedState);
}
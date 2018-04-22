<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 25/01/18
 * Time: 00:04
 */

namespace CloudRail\Error;

use \Error as Error;

class IllegalArgumentError extends Error {
    public function __construct(string $message) {
        Error::__construct("Illegal argument used:\n" . $message);
    }
}
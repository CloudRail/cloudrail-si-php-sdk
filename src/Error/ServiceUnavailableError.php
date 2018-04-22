<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 10/02/18
 * Time: 04:10
 */

namespace CloudRail\Error;

use \Error as Error;

class ServiceUnavailableError extends Error {
    public function __construct(string $message) {
        Error::__construct("ServiceUnavailableError:\n" . $message);
    }
}
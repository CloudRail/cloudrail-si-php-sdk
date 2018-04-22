<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 10/02/18
 * Time: 04:09
 */

namespace CloudRail\Error;

use \Error as Error;

class NotFoundError extends Error{
    public function __construct(string $message) {
        Error::__construct("NotFoundError:\n" . $message);
    }
}
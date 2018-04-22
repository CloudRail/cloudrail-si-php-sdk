<?php
/**
 * Created by PhpStorm.
 * User: felipe
 * Date: 10/12/17
 * Time: 20:57
 */

namespace CloudRail\Error;

use \Error as Error;

class InternalError extends Error {

    public function __construct(string $message) {
        Error::__construct("An internal error has occured which you probably cannot fix. ".
            "We'd very much appreciate it if you would report it to the CloudRail team. The error message is:\n".
            $message);
    }
}